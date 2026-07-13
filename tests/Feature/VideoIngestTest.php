<?php

namespace Tests\Feature;

use App\Models\PipelineJob;
use App\Models\Video;
use App\Services\FfprobeService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Mockery;
use Tests\CreatesFakeMedia;
use Tests\TestCase;

class VideoIngestTest extends TestCase
{
    use CreatesFakeMedia;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    /** Bind a fake ffprobe so tests don't need the real binary installed. */
    private function fakeFfprobe(int $duration): void
    {
        $mock = Mockery::mock(FfprobeService::class);
        $mock->shouldReceive('durationSeconds')->andReturn($duration);
        $this->app->instance(FfprobeService::class, $mock);
    }

    public function test_valid_upload_is_stored_and_creates_rows(): void
    {
        $this->fakeFfprobe(120);

        $response = $this->postJson('/api/videos', [
            'video' => $this->fakeVideoUpload('my-podcast.mp4'),
        ]);

        $response->assertCreated()
            ->assertJson(['status' => 'ingested', 'duration_seconds' => 120]);

        $video = Video::firstOrFail();
        $this->assertSame('upload', $video->source_type);
        $this->assertSame('my-podcast.mp4', $video->source_ref);

        // Path is server-generated (UUID), never the client name.
        $this->assertMatchesRegularExpression(
            '#^videos/[0-9a-f\-]{36}/source\.mp4$#',
            $video->storage_path,
        );
        Storage::disk('local')->assertExists($video->storage_path);

        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id,
            'stage' => 'ingest',
            'status' => 'done',
        ]);
    }

    public function test_non_video_content_is_rejected_by_magic_bytes(): void
    {
        // A .mp4 name but plain-text content — extension lies, magic bytes don't.
        $response = $this->postJson('/api/videos', [
            'video' => $this->fakeVideoUpload('totally-a-video.mp4', 'text/plain'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Video::count());
    }

    public function test_over_duration_video_is_rejected_and_cleaned_up(): void
    {
        // Cap is 3h default; return 4h from ffprobe.
        $this->fakeFfprobe(4 * 3600);

        $response = $this->postJson('/api/videos', [
            'video' => $this->fakeVideoUpload('long.mp4'),
        ]);

        $response->assertStatus(422);
        $this->assertSame(0, Video::count());
        // The rejected file must not linger on disk (retention discipline).
        $this->assertEmpty(Storage::disk('local')->allFiles());
    }

    /**
     * Spec DoD: "All ffmpeg calls use argument arrays, verified with a
     * malicious filename test case." A filename crafted for shell/path
     * injection must never influence the stored path, and the shell
     * metacharacters must be inert (they never reach a shell because we
     * discard the client name entirely).
     */
    public function test_malicious_filename_cannot_escape_storage_path(): void
    {
        $this->fakeFfprobe(30);

        $evil = '"; rm -rf / #/../../../../etc/passwd$(whoami).mp4';

        $response = $this->postJson('/api/videos', [
            'video' => $this->fakeVideoUpload($evil),
        ]);

        $response->assertCreated();
        $video = Video::firstOrFail();

        // Stored path contains only our UUID scheme — no trace of the payload.
        $this->assertMatchesRegularExpression(
            '#^videos/[0-9a-f\-]{36}/source\.mp4$#',
            $video->storage_path,
        );
        $this->assertStringNotContainsString('rm -rf', $video->storage_path);
        $this->assertStringNotContainsString('..', $video->storage_path);
        $this->assertStringNotContainsString('passwd', $video->storage_path);

        // The original name is kept only as an inert label, and Laravel has
        // already reduced it to a basename (no path components survive) —
        // a defense-in-depth bonus on top of our discard-the-name policy.
        $this->assertStringNotContainsString('/', $video->source_ref);
        $this->assertStringNotContainsString('\\', $video->source_ref);
    }

    public function test_missing_video_field_is_a_validation_error(): void
    {
        $this->postJson('/api/videos', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('video');
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
