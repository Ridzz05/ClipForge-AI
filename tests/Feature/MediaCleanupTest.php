<?php

namespace Tests\Feature;

use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use App\Services\MediaCleanupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class MediaCleanupTest extends TestCase
{
    use RefreshDatabase;

    private function renderedExport(string $status = Export::STATUS_RENDERED): Export
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'a.mp4', 'status' => 'reviewing',
            'storage_path' => 'videos/a/source.mp4',
        ]);
        Storage::disk('local')->put('videos/a/source.mp4', str_repeat('s', 100));
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'x', 'status' => ClipCandidate::STATUS_EXPORTED,
        ]);
        $export = Export::create([
            'clip_candidate_id' => $candidate->id, 'aspect_ratio' => '9:16', 'status' => $status,
        ]);
        $final = "exports/{$export->id}/final.mp4";
        $export->update(['output_path' => $final]);
        Storage::disk('local')->put($final, str_repeat('f', 50));
        Storage::disk('local')->put("exports/{$export->id}/seg_0.mp4", str_repeat('x', 30));
        Storage::disk('local')->put("exports/{$export->id}/captions.ass", str_repeat('x', 10));

        return $export;
    }

    public function test_usage_counts_bytes_and_flags_only_non_deliverable_files_as_reclaimable(): void
    {
        Storage::fake('local');
        $this->renderedExport();

        $usage = app(MediaCleanupService::class)->usage();

        $this->assertSame(190, $usage['total_bytes']);
        $this->assertSame(40, $usage['reclaimable_bytes']);
        $this->assertSame(100, $usage['videos'][0]['source_bytes']);
        $this->assertSame(90, $usage['videos'][0]['export_bytes']);
    }

    public function test_purge_keeps_final_clip_and_skips_exports_still_rendering(): void
    {
        Storage::fake('local');
        $done = $this->renderedExport();
        $busy = $this->renderedExport(Export::STATUS_RENDERING);

        $freed = app(MediaCleanupService::class)->purgeWorkFiles();

        $this->assertSame(40, $freed);
        Storage::disk('local')->assertExists($done->output_path);
        Storage::disk('local')->assertMissing("exports/{$done->id}/seg_0.mp4");
        Storage::disk('local')->assertExists("exports/{$busy->id}/seg_0.mp4");
    }

    public function test_projects_page_renders_and_clean_button_frees_work_files(): void
    {
        Storage::fake('local');
        $export = $this->renderedExport();

        $this->get('/projects')->assertOk()->assertSee('Proyek')->assertSee('a.mp4');

        Livewire::test(\App\Livewire\Projects::class)->call('cleanWorkFiles');

        Storage::disk('local')->assertMissing("exports/{$export->id}/captions.ass");
        Storage::disk('local')->assertExists($export->output_path);
    }
}
