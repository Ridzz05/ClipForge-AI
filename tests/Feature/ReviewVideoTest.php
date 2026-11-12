<?php

namespace Tests\Feature;

use App\Livewire\ReviewVideo;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Livewire\Livewire;
use Tests\TestCase;

class ReviewVideoTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake(); // don't run ReframeJob on approve
    }

    private function videoWithCandidate(string $status = ClipCandidate::STATUS_PENDING): array
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'ep.mp4', 'status' => 'reviewing',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 5000, 'end_ms' => 35000,
            'hook_score' => 88, 'score_rationale' => 'strong opening hook', 'status' => $status,
        ]);

        return [$video, $candidate];
    }

    public function test_renders_candidates_with_score_and_rationale(): void
    {
        [$video] = $this->videoWithCandidate();

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->assertOk()
            ->assertSee('88')
            ->assertSee('strong opening hook');
    }

    public function test_approve_creates_export_and_dispatches_render(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->call('approve', $candidate->id)
            ->assertHasNoErrors();

        $this->assertSame(ClipCandidate::STATUS_APPROVED, $candidate->fresh()->status);
        $this->assertSame(1, Export::where('clip_candidate_id', $candidate->id)->count());
        Queue::assertPushed(\App\Jobs\ReframeJob::class);
    }

    public function test_approve_persists_the_chosen_cta_on_the_export(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->set('ctaText', "Pablo Sanchez is back and he's still HIM")
            ->call('approve', $candidate->id)
            ->assertHasNoErrors();

        $export = Export::where('clip_candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame("Pablo Sanchez is back and he's still HIM", $export->cta_text);
    }

    public function test_approve_passes_headline_and_music_to_the_export(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        $music = \App\Models\Asset::create([
            'kind' => \App\Models\Asset::KIND_MUSIC,
            'name' => 'Lofi Bed',
            'path' => 'assets/music/lofi.mp3',
            'mime' => 'audio/mpeg',
            'size_bytes' => 1024,
        ]);

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->set('headlineText', '  Judul Hook  ')
            ->set('headlineStyle', 'sticker')
            ->set('musicAssetId', $music->id)
            ->set('musicVolume', 35)
            ->call('approve', $candidate->id)
            ->assertHasNoErrors();

        $export = Export::where('clip_candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame('Judul Hook', $export->headline_text);
        $this->assertSame('sticker', $export->headline_style);
        $this->assertSame($music->id, $export->music_asset_id);
        $this->assertSame(35, $export->music_volume);
    }

    public function test_approve_rejects_a_music_id_that_is_not_a_music_asset(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        $image = \App\Models\Asset::create([
            'kind' => \App\Models\Asset::KIND_IMAGE,
            'name' => 'Logo',
            'path' => 'assets/images/logo.png',
            'mime' => 'image/png',
            'size_bytes' => 512,
        ]);

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->set('musicAssetId', $image->id)
            ->call('approve', $candidate->id)
            ->assertHasErrors('musicAssetId');

        $this->assertSame(ClipCandidate::STATUS_PENDING, $candidate->fresh()->status);
        $this->assertSame(0, Export::count());
    }

    public function test_approve_rejects_an_overlong_headline(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->set('headlineText', str_repeat('a', 81))
            ->call('approve', $candidate->id)
            ->assertHasErrors('headlineText');

        $this->assertSame(0, Export::count());
    }

    public function test_reject_marks_candidate_rejected_without_export(): void
    {
        [$video, $candidate] = $this->videoWithCandidate();

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->call('reject', $candidate->id);

        $this->assertSame(ClipCandidate::STATUS_REJECTED, $candidate->fresh()->status);
        $this->assertSame(0, Export::count());
        Queue::assertNotPushed(\App\Jobs\ReframeJob::class);
    }

    public function test_cannot_act_on_a_candidate_from_another_video(): void
    {
        [$video] = $this->videoWithCandidate();

        // A candidate belonging to a DIFFERENT video.
        $other = Video::create([
            'source_type' => 'upload', 'source_ref' => 'y.mp4', 'status' => 'reviewing',
            'duration_seconds' => 60, 'storage_path' => 'videos/y/source.mp4',
        ]);
        $foreign = ClipCandidate::create([
            'video_id' => $other->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 50, 'score_rationale' => 'x', 'status' => ClipCandidate::STATUS_PENDING,
        ]);

        Livewire::test(ReviewVideo::class, ['video' => $video])
            ->call('approve', $foreign->id);

        // Foreign candidate untouched; no export created.
        $this->assertSame(ClipCandidate::STATUS_PENDING, $foreign->fresh()->status);
        $this->assertSame(0, Export::count());
    }

    public function test_approving_already_exported_candidate_shows_error(): void
    {
        [$video, $candidate] = $this->videoWithCandidate(ClipCandidate::STATUS_EXPORTED);

        $component = Livewire::test(ReviewVideo::class, ['video' => $video])
            ->call('approve', $candidate->id);

        $this->assertNotEmpty($component->get('error'));
        $this->assertSame(0, Export::count());
    }
}
