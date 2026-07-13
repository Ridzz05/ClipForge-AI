<?php

namespace Tests\Feature;

use App\Jobs\ExportDeliverJob;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use App\Services\Reframe\FfmpegService;
use App\Services\Reframe\WatermarkCommandBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ExportDeliverTest extends TestCase
{
    use RefreshDatabase;

    private array $capturedArgs = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');

        $ffmpeg = Mockery::mock(FfmpegService::class);
        $ffmpeg->shouldReceive('run')->andReturnUsing(function (array $args) {
            $this->capturedArgs = $args;
            $out = $args[array_key_last($args)];
            @mkdir(dirname($out), 0777, true);
            file_put_contents($out, 'watermarked-mp4');

            return 'ok';
        });
        $this->app->instance(FfmpegService::class, $ffmpeg);
    }

    private function makeRenderedExport(): Export
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'exporting',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'good',
            'status' => ClipCandidate::STATUS_APPROVED,
        ]);

        $reframed = "exports/1/reframed.mp4";
        Storage::disk('local')->put($reframed, 'reframed-mp4');

        return Export::create([
            'clip_candidate_id' => $candidate->id, 'aspect_ratio' => '9:16',
            'caption_style' => 'default', 'status' => Export::STATUS_RENDERED,
            'output_path' => $reframed,
        ]);
    }

    private function runJob(Export $export): void
    {
        (new ExportDeliverJob($export->id))->handle(
            $this->app->make(WatermarkCommandBuilder::class),
            $this->app->make(FfmpegService::class),
        );
    }

    public function test_without_watermark_configured_marks_exported_directly(): void
    {
        config(['autoclip.render.watermark_path' => null]);
        $export = $this->makeRenderedExport();

        $this->runJob($export);

        $export->refresh();
        $this->assertFalse($export->watermark_applied);
        $this->assertSame(Export::STATUS_RENDERED, $export->status);
        $this->assertSame(ClipCandidate::STATUS_EXPORTED, $export->clipCandidate->status);
        $this->assertEmpty($this->capturedArgs); // ffmpeg not called
        $this->assertDatabaseHas('pipeline_jobs', ['stage' => 'export', 'status' => 'done']);
    }

    public function test_with_watermark_applies_overlay_and_swaps_output(): void
    {
        // A real watermark file on disk so is_file() passes.
        $wm = tempnam(sys_get_temp_dir(), 'wm_').'.png';
        file_put_contents($wm, 'PNGDATA');
        config(['autoclip.render.watermark_path' => $wm]);

        $export = $this->makeRenderedExport();
        $originalPath = $export->output_path;

        $this->runJob($export);

        $export->refresh();
        $this->assertTrue($export->watermark_applied);
        $this->assertNotSame($originalPath, $export->output_path);
        $this->assertStringContainsString('-final.mp4', $export->output_path);
        Storage::disk('local')->assertExists($export->output_path);
        // Intermediate reframed file cleaned up.
        Storage::disk('local')->assertMissing($originalPath);
        $this->assertSame(ClipCandidate::STATUS_EXPORTED, $export->clipCandidate->status);

        @unlink($wm);
    }

    public function test_export_without_rendered_file_is_noop(): void
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'exporting',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'good', 'status' => ClipCandidate::STATUS_APPROVED,
        ]);
        $export = Export::create([
            'clip_candidate_id' => $candidate->id, 'status' => Export::STATUS_QUEUED,
            'output_path' => null,
        ]);

        $this->runJob($export);
        $this->assertSame(ClipCandidate::STATUS_APPROVED, $candidate->fresh()->status);
    }

    public function test_failed_hook_marks_export_and_pipeline_failed(): void
    {
        $export = $this->makeRenderedExport();

        (new ExportDeliverJob($export->id))->failed(new RuntimeException('overlay boom'));

        $export->refresh();
        $this->assertSame(Export::STATUS_FAILED, $export->status);
        $this->assertSame('overlay boom', $export->last_error);
        $this->assertDatabaseHas('pipeline_jobs', [
            'stage' => 'export', 'status' => 'failed', 'last_error' => 'overlay boom',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
