<?php

namespace Tests\Feature;

use App\Jobs\ReframeJob;
use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\Video;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class ClipCandidateReviewTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Queue::fake();
    }

    private function makeCandidate(string $status = ClipCandidate::STATUS_PENDING): ClipCandidate
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'reviewing',
            'duration_seconds' => 600, 'storage_path' => 'videos/x/source.mp4',
        ]);

        return ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 30000,
            'hook_score' => 80, 'score_rationale' => 'good', 'status' => $status,
        ]);
    }

    public function test_approving_creates_export_and_dispatches_reframe(): void
    {
        $candidate = $this->makeCandidate();

        $response = $this->postJson("/api/candidates/{$candidate->id}/approve");

        $response->assertStatus(202)
            ->assertJson(['candidate_id' => $candidate->id, 'status' => 'approved']);

        $this->assertSame('approved', $candidate->fresh()->status);

        $export = Export::where('clip_candidate_id', $candidate->id)->firstOrFail();
        $this->assertSame(Export::STATUS_QUEUED, $export->status);
        $this->assertSame('9:16', $export->aspect_ratio);

        Queue::assertPushed(ReframeJob::class, fn ($job) => $job->exportId === $export->id);
    }

    public function test_rejecting_marks_candidate_and_dispatches_nothing(): void
    {
        $candidate = $this->makeCandidate();

        $this->postJson("/api/candidates/{$candidate->id}/reject")
            ->assertOk()
            ->assertJson(['status' => 'rejected']);

        $this->assertSame('rejected', $candidate->fresh()->status);
        $this->assertSame(0, Export::count());
        Queue::assertNotPushed(ReframeJob::class);
    }

    public function test_approving_an_already_exported_candidate_is_a_conflict(): void
    {
        $candidate = $this->makeCandidate(ClipCandidate::STATUS_EXPORTED);

        $this->postJson("/api/candidates/{$candidate->id}/approve")
            ->assertStatus(409);

        Queue::assertNotPushed(ReframeJob::class);
    }

    public function test_unknown_candidate_returns_404(): void
    {
        $this->postJson('/api/candidates/999999/approve')->assertNotFound();
    }

    public function test_approving_with_manual_crop_x_stores_crop_position(): void
    {
        $candidate = $this->makeCandidate();
        $service = new \App\Services\ClipReviewService();

        $export = $service->approve($candidate, 'CTA', 'default', 960, 'single', 0.35);

        $this->assertEquals(0.35, $export->manual_crop_x);
    }
}
