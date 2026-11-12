<?php

namespace Tests\Feature;

use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PruneMediaCommandTest extends TestCase
{
    use RefreshDatabase;

    public function test_prune_is_dry_run_without_force_and_deletes_old_failed_videos_with_force(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('videos/old/source.mp4', 'x');

        $old = Video::create([
            'source_type' => 'upload', 'source_ref' => 'a.mp4', 'status' => 'failed',
            'storage_path' => 'videos/old/source.mp4',
        ]);
        $recent = Video::create([
            'source_type' => 'upload', 'source_ref' => 'b.mp4', 'status' => 'failed',
            'storage_path' => 'videos/new/source.mp4',
        ]);
        Video::whereKey($old->id)->update(['updated_at' => now()->subDays(40)]);

        $this->artisan('autoclip:prune')->assertSuccessful();
        $this->assertDatabaseHas('videos', ['id' => $old->id]);
        Storage::disk('local')->assertExists('videos/old/source.mp4');

        $this->artisan('autoclip:prune', ['--force' => true])->assertSuccessful();
        $this->assertDatabaseMissing('videos', ['id' => $old->id]);
        $this->assertDatabaseHas('videos', ['id' => $recent->id]);
        Storage::disk('local')->assertMissing('videos/old/source.mp4');
    }
}
