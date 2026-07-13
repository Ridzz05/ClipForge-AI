<?php

namespace Tests\Feature;

use App\Jobs\IngestUrlJob;
use App\Jobs\TranscribeJob;
use App\Models\Video;
use App\Services\FfprobeService;
use App\Services\VideoIngestService;
use App\Services\YtDlpService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\TestCase;

class IngestUrlJobTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        Queue::fake(); // capture TranscribeJob handoff

        $ffprobe = Mockery::mock(FfprobeService::class);
        $ffprobe->shouldReceive('durationSeconds')->andReturn(300);
        $this->app->instance(FfprobeService::class, $ffprobe);
    }

    private function fakeYtDlp(bool $downloadThrows = false): void
    {
        $mock = Mockery::mock(YtDlpService::class);
        $mock->shouldReceive('assertSafeUrl')->andReturnNull();

        if ($downloadThrows) {
            $mock->shouldReceive('download')->andThrow(new \RuntimeException('yt-dlp: video unavailable'));
        } else {
            $mock->shouldReceive('download')->andReturnUsing(function ($url, $absDir, $base) {
                @mkdir($absDir, 0777, true);
                $path = rtrim($absDir, '/\\').DIRECTORY_SEPARATOR.$base.'.mp4';
                file_put_contents($path, 'downloaded-video-bytes');

                return $path;
            });
        }
        $this->app->instance(YtDlpService::class, $mock);
    }

    /** A pending url video, as created synchronously on submit. */
    private function pendingVideo(string $url = 'https://youtube.com/watch?v=abc'): Video
    {
        return $this->app->make(VideoIngestService::class)->createPendingUrlVideo($url);
    }

    private function runJob(int $videoId): void
    {
        (new IngestUrlJob($videoId))->handle(
            $this->app->make(YtDlpService::class),
            $this->app->make(VideoIngestService::class),
        );
    }

    public function test_pending_video_is_created_immediately_on_submit(): void
    {
        $video = $this->pendingVideo('https://youtube.com/watch?v=xyz');

        $this->assertSame('url', $video->source_type);
        $this->assertSame('downloading', $video->status);
        $this->assertNull($video->storage_path);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id, 'stage' => 'ingest', 'status' => 'queued',
        ]);
    }

    public function test_successful_download_completes_ingest_and_starts_pipeline(): void
    {
        $this->fakeYtDlp();
        $video = $this->pendingVideo();

        $this->runJob($video->id);

        $video->refresh();
        $this->assertSame('ingested', $video->status);
        $this->assertSame(300, $video->duration_seconds);
        $this->assertMatchesRegularExpression('#^videos/url-\d+/source\.mp4$#', $video->storage_path);
        Storage::disk('local')->assertExists($video->storage_path);

        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id, 'stage' => 'ingest', 'status' => 'done',
        ]);
        Queue::assertPushed(TranscribeJob::class, fn ($j) => $j->videoId === $video->id);
    }

    public function test_download_failure_is_visible_on_the_video(): void
    {
        $this->fakeYtDlp(downloadThrows: true);
        $video = $this->pendingVideo();

        // The job throws; the queue would then call failed(). Simulate both.
        try {
            $this->runJob($video->id);
        } catch (\Throwable $e) {
            (new IngestUrlJob($video->id))->failed($e);
        }

        $video->refresh();
        $this->assertSame('failed', $video->status);
        $this->assertNotNull($video->last_error);
        $this->assertStringContainsString('video unavailable', $video->last_error);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id, 'stage' => 'ingest', 'status' => 'failed',
        ]);
        Queue::assertNotPushed(TranscribeJob::class);
    }

    public function test_over_duration_download_marks_video_failed(): void
    {
        $ffprobe = Mockery::mock(FfprobeService::class);
        $ffprobe->shouldReceive('durationSeconds')->andReturn(4 * 3600);
        $this->app->instance(FfprobeService::class, $ffprobe);
        $this->fakeYtDlp();

        $video = $this->pendingVideo();

        try {
            $this->runJob($video->id);
        } catch (\Throwable $e) {
            (new IngestUrlJob($video->id))->failed($e);
        }

        $video->refresh();
        $this->assertSame('failed', $video->status);
        $this->assertStringContainsString('maximum', $video->last_error);
        // The downloaded file was cleaned up.
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    public function test_missing_video_is_a_noop(): void
    {
        $this->fakeYtDlp();
        $this->runJob(999999); // should not throw
        $this->assertSame(0, Video::count());
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
