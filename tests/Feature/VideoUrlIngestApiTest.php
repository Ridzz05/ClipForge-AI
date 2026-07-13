<?php

namespace Tests\Feature;

use App\Jobs\IngestUrlJob;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class VideoUrlIngestApiTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    public function test_accepts_a_public_url_and_queues_download(): void
    {
        $this->postJson('/api/videos/url', ['url' => 'https://www.youtube.com/watch?v=abc'])
            ->assertStatus(202)
            ->assertJsonStructure(['id', 'status', 'message']);

        $video = \App\Models\Video::where('source_type', 'url')->firstOrFail();
        $this->assertSame('downloading', $video->status);

        Queue::assertPushed(IngestUrlJob::class,
            fn ($j) => $j->videoId === $video->id);
    }

    public function test_rejects_ssrf_url(): void
    {
        $this->postJson('/api/videos/url', ['url' => 'http://127.0.0.1/x.mp4'])
            ->assertStatus(422);

        Queue::assertNotPushed(IngestUrlJob::class);
    }

    public function test_rejects_non_http_scheme(): void
    {
        $this->postJson('/api/videos/url', ['url' => 'file:///etc/passwd'])
            ->assertStatus(422);

        Queue::assertNotPushed(IngestUrlJob::class);
    }

    public function test_requires_a_url(): void
    {
        $this->postJson('/api/videos/url', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors('url');

        Queue::assertNotPushed(IngestUrlJob::class);
    }
}
