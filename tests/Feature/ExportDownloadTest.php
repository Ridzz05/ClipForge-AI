<?php

namespace Tests\Feature;

use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ExportDownloadTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
    }

    private function makeExport(string $status, ?string $path): Export
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'done',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);
        $candidate = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'good', 'status' => ClipCandidate::STATUS_EXPORTED,
        ]);

        if ($path) {
            Storage::disk('local')->put($path, 'final-mp4-bytes');
        }

        return Export::create([
            'clip_candidate_id' => $candidate->id, 'status' => $status, 'output_path' => $path,
        ]);
    }

    public function test_downloads_a_rendered_export(): void
    {
        $export = $this->makeExport(Export::STATUS_RENDERED, 'exports/1/final.mp4');

        $response = $this->get("/api/exports/{$export->id}/download");

        $response->assertOk();
        $response->assertHeader('content-type', 'video/mp4');
        $this->assertStringContainsString(
            "clip-{$export->id}.mp4",
            $response->headers->get('content-disposition'),
        );
    }

    public function test_unready_export_is_a_conflict(): void
    {
        $export = $this->makeExport(Export::STATUS_RENDERING, null);

        $this->getJson("/api/exports/{$export->id}/download")->assertStatus(409);
    }

    public function test_missing_file_is_404(): void
    {
        // Marked rendered but the file isn't on disk.
        $export = $this->makeExport(Export::STATUS_RENDERED, 'exports/1/gone.mp4');
        Storage::disk('local')->delete('exports/1/gone.mp4');

        $this->getJson("/api/exports/{$export->id}/download")->assertStatus(404);
    }

    public function test_unknown_export_is_404(): void
    {
        $this->getJson('/api/exports/999999/download')->assertNotFound();
    }
}
