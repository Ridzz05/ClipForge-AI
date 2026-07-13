<?php

namespace Tests\Feature;

use App\Livewire\Dashboard;
use App\Models\Video;
use App\Services\FfprobeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Mockery;
use Tests\CreatesFakeMedia;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use CreatesFakeMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake(); // don't run the transcribe job on upload

        // ffprobe faked so no real binary is needed.
        $ffprobe = Mockery::mock(FfprobeService::class);
        $ffprobe->shouldReceive('durationSeconds')->andReturn(120);
        $this->app->instance(FfprobeService::class, $ffprobe);
    }

    public function test_dashboard_renders(): void
    {
        Livewire::test(Dashboard::class)
            ->assertOk()
            ->assertSee('Dashboard');
    }

    public function test_upload_ingests_video_through_the_service(): void
    {
        $file = $this->fakeLivewireVideoUpload('my-clip.mp4');

        Livewire::test(Dashboard::class)
            ->set('upload', $file)
            ->call('save')
            ->assertHasNoErrors();

        // The real outcome: the video was ingested via the service.
        $this->assertSame(1, Video::count());
        $video = Video::first();
        $this->assertSame('my-clip.mp4', $video->source_ref);
        $this->assertSame(120, $video->duration_seconds);
    }

    public function test_upload_rejects_a_disallowed_file_type(): void
    {
        // NB: Livewire's fake upload derives MIME from the extension, so we use
        // a plainly-wrong extension here. Content-based (magic-byte) rejection
        // is covered against the real UploadedFile path in VideoIngestTest.
        $file = \Illuminate\Http\UploadedFile::fake()->create('notes.txt', 10, 'text/plain');

        $component = Livewire::test(Dashboard::class)
            ->set('upload', $file)
            ->call('save');

        $this->assertNotEmpty($component->get('uploadError'));
        $this->assertSame(0, Video::count());
    }

    public function test_lists_existing_videos_with_candidate_count(): void
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'webinar.mp4', 'status' => 'reviewing',
            'duration_seconds' => 1800, 'storage_path' => 'videos/x/source.mp4',
        ]);
        \App\Models\ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'x', 'status' => 'pending',
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('webinar.mp4')
            ->assertSee('Review');
    }

    public function test_url_ingest_dispatches_download_job(): void
    {
        Livewire::test(Dashboard::class)
            ->set('url', 'https://www.youtube.com/watch?v=abc123')
            ->call('ingestUrl')
            ->assertHasNoErrors();

        // A tracked video row is created immediately (status=downloading).
        $video = Video::where('source_type', 'url')->firstOrFail();
        $this->assertSame('downloading', $video->status);
        $this->assertSame('https://www.youtube.com/watch?v=abc123', $video->source_ref);

        Queue::assertPushed(\App\Jobs\IngestUrlJob::class,
            fn ($j) => $j->videoId === $video->id);
    }

    public function test_url_ingest_rejects_ssrf_url_without_dispatching(): void
    {
        $component = Livewire::test(Dashboard::class)
            ->set('url', 'http://169.254.169.254/latest/meta-data/')
            ->call('ingestUrl');

        $this->assertNotEmpty($component->get('urlError'));
        Queue::assertNotPushed(\App\Jobs\IngestUrlJob::class);
        // No stray video row for a rejected URL.
        $this->assertSame(0, Video::where('source_type', 'url')->count());
    }

    public function test_url_ingest_rejects_empty_url(): void
    {
        Livewire::test(Dashboard::class)
            ->set('url', '   ')
            ->call('ingestUrl');

        Queue::assertNotPushed(\App\Jobs\IngestUrlJob::class);
    }

    public function test_failed_video_shows_its_failure_reason(): void
    {
        Video::create([
            'source_type' => 'url', 'source_ref' => 'https://x/y', 'status' => 'failed',
            'last_error' => 'Download failed: video unavailable',
            'storage_path' => null,
        ]);

        Livewire::test(Dashboard::class)
            ->assertSee('Gagal')
            ->assertSee('Download failed: video unavailable');
    }

    public function test_failure_reason_falls_back_to_pipeline_job_error(): void
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'failed',
            'storage_path' => 'videos/x/source.mp4',
        ]);
        // No video.last_error, but a failed pipeline stage carries the reason.
        \App\Models\PipelineJob::create([
            'video_id' => $video->id, 'stage' => 'transcribe', 'status' => 'failed',
            'last_error' => 'Whisper service returned HTTP 500',
        ]);

        $this->assertSame('Whisper service returned HTTP 500', $video->failureReason());

        Livewire::test(Dashboard::class)
            ->assertSee('Whisper service returned HTTP 500');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
