<?php

namespace Tests\Feature;

use App\Livewire\ActivityFeed;
use App\Models\PipelineJob;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ActivityFeedTest extends TestCase
{
    use RefreshDatabase;

    private function videoWithJob(string $stage, string $status, ?string $error = null): Video
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'ep.mp4', 'status' => 'transcribing',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        PipelineJob::create([
            'video_id' => $video->id, 'stage' => $stage, 'status' => $status,
            'last_error' => $error,
        ]);

        return $video;
    }

    public function test_empty_state(): void
    {
        Livewire::test(ActivityFeed::class)
            ->assertOk()
            ->assertSee('Belum ada riwayat aktivitas');
    }

    public function test_shows_recent_pipeline_events(): void
    {
        $this->videoWithJob('transcribe', 'done');

        Livewire::test(ActivityFeed::class)
            ->assertSee('Transcribe')
            ->assertSee('Selesai')
            ->assertSee('ep.mp4');
    }

    public function test_shows_failure_reason_in_the_feed(): void
    {
        $this->videoWithJob('score', 'failed', 'Ollama returned HTTP 500');

        Livewire::test(ActivityFeed::class)
            ->assertSee('Gagal')
            ->assertSee('Ollama returned HTTP 500');
    }

    public function test_dashboard_embeds_the_activity_feed(): void
    {
        $this->videoWithJob('transcribe', 'running');

        Livewire::test(\App\Livewire\Dashboard::class)
            ->assertSee('Aktivitas Pipeline Terbaru');
    }
}
