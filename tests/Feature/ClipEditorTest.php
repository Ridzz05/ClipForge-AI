<?php

namespace Tests\Feature;

use App\Livewire\ClipEditor;
use App\Models\Asset;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ClipEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeCandidate(): ClipCandidate
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'webinar.mp4', 'status' => 'reviewing',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);

        return ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 5000, 'end_ms' => 35000,
            'hook_score' => 80, 'score_rationale' => 'good', 'status' => ClipCandidate::STATUS_PENDING,
        ]);
    }

    public function test_editor_renders_the_headline_and_music_controls(): void
    {
        $candidate = $this->makeCandidate();

        Livewire::test(ClipEditor::class, ['candidate' => $candidate])
            ->assertOk()
            ->assertSee('Auto Headline')
            ->assertSee('Tanpa musik')
            ->assertSee('Unggah musik di Aset');
    }

    public function test_export_forwards_headline_and_music_to_the_export(): void
    {
        $candidate = $this->makeCandidate();

        $music = Asset::create([
            'kind' => Asset::KIND_MUSIC, 'name' => 'Lofi Bed',
            'path' => 'assets/music/lofi.mp3', 'mime' => 'audio/mpeg', 'size_bytes' => 1024,
        ]);

        Livewire::test(ClipEditor::class, ['candidate' => $candidate])
            ->set('headlineText', 'Judul Hook')
            ->set('headlineStyle', 'highlight')
            ->set('musicAssetId', $music->id)
            ->set('musicVolume', 40)
            ->call('saveAndApprove')
            ->assertHasNoErrors();

        $export = Export::where('clip_candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame('Judul Hook', $export->headline_text);
        $this->assertSame('highlight', $export->headline_style);
        $this->assertSame($music->id, $export->music_asset_id);
        $this->assertSame(40, $export->music_volume);
        Queue::assertPushed(\App\Jobs\ReframeJob::class);
    }
}
