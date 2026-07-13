<?php

namespace Tests\Feature;

use App\Jobs\ReframeJob;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use App\Services\FfprobeService;
use App\Services\Reframe\FaceTrackingService;
use App\Services\Reframe\FfmpegService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ReframeJobTest extends TestCase
{
    use RefreshDatabase;

    private array $capturedFfmpegArgs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake(); // don't run Stage 5 handoff

        // ffprobe -> fixed landscape dimensions.
        $ffprobe = Mockery::mock(FfprobeService::class);
        $ffprobe->shouldReceive('dimensions')->andReturn(['width' => 1920, 'height' => 1080]);
        $this->app->instance(FfprobeService::class, $ffprobe);

        // face tracking -> a couple of centres.
        $faces = Mockery::mock(FaceTrackingService::class);
        $faces->shouldReceive('sampleCenters')->andReturn([
            ['t_ms' => 0, 'cx' => 0.5],
            ['t_ms' => 1000, 'cx' => 0.6],
        ]);
        $this->app->instance(FaceTrackingService::class, $faces);

        // ffmpeg -> capture args, write a fake output file, don't really run.
        $ffmpeg = Mockery::mock(FfmpegService::class);
        $ffmpeg->shouldReceive('run')->andReturnUsing(function (array $args) {
            $this->capturedFfmpegArgs = $args;
            // Simulate ffmpeg producing the output file (last arg).
            $out = $args[array_key_last($args)];
            @mkdir(dirname($out), 0777, true);
            file_put_contents($out, 'fake-rendered-mp4');

            return 'ffmpeg stderr log';
        });
        $this->app->instance(FfmpegService::class, $ffmpeg);
    }

    private function makeApprovedExport(): Export
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'reviewing',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        Storage::disk('local')->put($video->storage_path, 'fake-media');

        $transcript = Transcript::create([
            'video_id' => $video->id, 'full_text' => 'hello world', 'language' => 'en',
        ]);
        TranscriptSegment::create([
            'transcript_id' => $transcript->id, 'start_ms' => 0, 'end_ms' => 2000,
            'text' => 'hello world',
            'words' => [
                ['word' => 'hello', 'start_ms' => 0, 'end_ms' => 500],
                ['word' => 'world', 'start_ms' => 500, 'end_ms' => 1500],
            ],
        ]);

        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'good',
            'status' => ClipCandidate::STATUS_APPROVED,
        ]);

        return Export::create([
            'clip_candidate_id' => $candidate->id, 'aspect_ratio' => '9:16',
            'caption_style' => 'default', 'status' => Export::STATUS_QUEUED,
        ]);
    }

    private function runJob(Export $export): void
    {
        (new ReframeJob($export->id))->handle(
            $this->app->make(FfprobeService::class),
            $this->app->make(FaceTrackingService::class),
            $this->app->make(\App\Services\Reframe\ReframePlanner::class),
            $this->app->make(\App\Services\Reframe\CaptionRenderer::class),
            $this->app->make(\App\Services\Reframe\ReframeCommandBuilder::class),
            $this->app->make(FfmpegService::class),
        );
    }

    public function test_renders_clip_and_marks_export_rendered(): void
    {
        $export = $this->makeApprovedExport();

        $this->runJob($export);

        $export->refresh();
        $this->assertSame(Export::STATUS_RENDERED, $export->status);
        $this->assertNotNull($export->output_path);
        $this->assertNotNull($export->rendered_at);
        Storage::disk('local')->assertExists($export->output_path);

        // An ASS caption file was generated for the clip.
        Storage::disk('local')->assertExists("exports/{$export->id}/captions.ass");

        $this->assertDatabaseHas('pipeline_jobs', [
            'stage' => 'reframe', 'status' => 'done',
        ]);

        // Hands off to Stage 5 (watermark + deliver) for the same export.
        Queue::assertPushed(\App\Jobs\ExportDeliverJob::class,
            fn ($job) => $job->exportId === $export->id);
    }

    public function test_ffmpeg_invoked_with_argument_array(): void
    {
        $export = $this->makeApprovedExport();
        $this->runJob($export);

        // Every captured arg is a string; the filter graph is present.
        $this->assertNotEmpty($this->capturedFfmpegArgs);
        foreach ($this->capturedFfmpegArgs as $arg) {
            $this->assertIsString($arg);
        }
        $filter = $this->capturedFfmpegArgs[array_search('-vf', $this->capturedFfmpegArgs, true) + 1];
        $this->assertStringContainsString('crop=', $filter);
        $this->assertStringContainsString('subtitles=', $filter);
    }

    public function test_failed_hook_marks_export_and_pipeline_failed(): void
    {
        $export = $this->makeApprovedExport();

        (new ReframeJob($export->id))->failed(new RuntimeException('ffmpeg boom'));

        $export->refresh();
        $this->assertSame(Export::STATUS_FAILED, $export->status);
        $this->assertSame('ffmpeg boom', $export->last_error);
        $this->assertDatabaseHas('pipeline_jobs', [
            'stage' => 'reframe', 'status' => 'failed', 'last_error' => 'ffmpeg boom',
        ]);
    }

    public function test_missing_export_is_a_noop(): void
    {
        // A non-existent export id must not throw.
        (new ReframeJob(999999))->handle(
            $this->app->make(FfprobeService::class),
            $this->app->make(FaceTrackingService::class),
            $this->app->make(\App\Services\Reframe\ReframePlanner::class),
            $this->app->make(\App\Services\Reframe\CaptionRenderer::class),
            $this->app->make(\App\Services\Reframe\ReframeCommandBuilder::class),
            $this->app->make(FfmpegService::class),
        );
        $this->assertSame(0, Export::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
