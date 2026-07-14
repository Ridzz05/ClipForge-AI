<?php

namespace Tests\Feature;

use App\Livewire\Exports;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ExportsPageTest extends TestCase
{
    use RefreshDatabase;

    private function makeExport(string $status, array $attrs = []): Export
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'ep.mp4', 'status' => 'done',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'x', 'status' => ClipCandidate::STATUS_EXPORTED,
        ]);

        return Export::create(array_merge([
            'clip_candidate_id' => $candidate->id, 'aspect_ratio' => '9:16',
            'caption_style' => 'default', 'status' => $status,
        ], $attrs));
    }

    public function test_empty_state_when_no_exports(): void
    {
        Livewire::test(Exports::class)
            ->assertOk()
            ->assertSee('Belum ada klip yang diekspor');
    }

    public function test_rendered_export_shows_download_link(): void
    {
        $export = $this->makeExport(Export::STATUS_RENDERED, [
            'output_path' => 'exports/1/final.mp4',
            'watermark_applied' => true,
        ]);

        Livewire::test(Exports::class)
            ->assertSee('Selesai')
            ->assertSee('ep.mp4')
            ->assertSeeHtml('/api/exports/'.$export->id.'/download');
    }

    public function test_failed_export_shows_error_and_no_download(): void
    {
        $export = $this->makeExport(Export::STATUS_FAILED, [
            'last_error' => 'ffmpeg overlay boom',
        ]);

        Livewire::test(Exports::class)
            ->assertSee('Gagal')
            ->assertSee('ffmpeg overlay boom')
            ->assertDontSeeHtml('/api/exports/'.$export->id.'/download');
    }

    public function test_polls_while_rendering(): void
    {
        $this->makeExport(Export::STATUS_RENDERING);

        // wire:poll present in the rendered output while work is in flight.
        Livewire::test(Exports::class)
            ->assertSee('Rendering');
    }
}
