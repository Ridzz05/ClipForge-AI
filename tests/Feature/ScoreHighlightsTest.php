<?php

namespace Tests\Feature;

use App\Jobs\ScoreHighlightsJob;
use App\Models\ClipCandidate;
use App\Models\Transcript;
use App\Models\TranscriptSegment;
use App\Models\Video;
use App\Services\Scoring\HighlightSchema;
use App\Services\Scoring\OllamaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class ScoreHighlightsTest extends TestCase
{
    use RefreshDatabase;

    private function makeVideoWithTranscript(int $segments = 3): Video
    {
        $video = Video::create([
            'source_type' => 'upload',
            'source_ref' => 'clip.mp4',
            'status' => 'transcribed',
            'duration_seconds' => 600,
            'storage_path' => 'videos/x/source.mp4',
        ]);

        $transcript = Transcript::create([
            'video_id' => $video->id,
            'full_text' => 'full text',
            'language' => 'en',
        ]);

        for ($i = 0; $i < $segments; $i++) {
            TranscriptSegment::create([
                'transcript_id' => $transcript->id,
                'start_ms' => $i * 5000,
                'end_ms' => ($i * 5000) + 4000,
                'text' => "segment {$i}",
                'words' => [],
            ]);
        }

        return $video;
    }

    /** Fake Ollama to return one valid highlight per batch. */
    private function fakeOllama(mixed $raw): void
    {
        $mock = Mockery::mock(OllamaService::class);
        $mock->shouldReceive('scoreBatch')->andReturn($raw);
        $this->app->instance(OllamaService::class, $mock);
    }

    private function runJob(Video $video): void
    {
        (new ScoreHighlightsJob($video->id))->handle(
            $this->app->make(OllamaService::class),
            $this->app->make(HighlightSchema::class),
        );
    }

    public function test_persists_validated_candidates_as_pending(): void
    {
        $video = $this->makeVideoWithTranscript();
        $this->fakeOllama(['highlights' => [
            ['start_ms' => 0, 'end_ms' => 30000, 'hook_score' => 88, 'rationale' => 'great hook'],
        ]]);

        $this->runJob($video);

        $candidate = ClipCandidate::where('video_id', $video->id)->firstOrFail();
        $this->assertSame(ClipCandidate::STATUS_PENDING, $candidate->status);
        $this->assertSame(88, $candidate->hook_score);
        $this->assertSame('great hook', $candidate->score_rationale);

        // Awaits human review before export (review gate).
        $this->assertSame('reviewing', $video->fresh()->status);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id, 'stage' => 'score', 'status' => 'done',
        ]);
    }

    public function test_injection_payload_in_llm_output_is_stored_as_inert_data(): void
    {
        $video = $this->makeVideoWithTranscript();
        // Model returns an object trying to smuggle command/path fields.
        $this->fakeOllama(['highlights' => [[
            'start_ms' => 0, 'end_ms' => 20000, 'hook_score' => 70,
            'rationale' => 'normal',
            'cmd' => 'rm -rf /', 'output_path' => '../../etc/passwd',
        ]]]);

        $this->runJob($video);

        $candidate = ClipCandidate::where('video_id', $video->id)->firstOrFail();
        // Only schema fields persisted; no smuggled attributes exist on the row.
        $this->assertNull($candidate->getAttribute('cmd'));
        $this->assertNull($candidate->getAttribute('output_path'));
        $this->assertSame('normal', $candidate->score_rationale);
    }

    public function test_retry_replaces_pending_but_keeps_approved(): void
    {
        $video = $this->makeVideoWithTranscript();

        // A human already approved one candidate from a prior run.
        $approved = ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 10000,
            'hook_score' => 95, 'score_rationale' => 'human pick',
            'status' => ClipCandidate::STATUS_APPROVED,
        ]);
        // And a stale pending one that should be replaced on re-run.
        ClipCandidate::create([
            'video_id' => $video->id, 'start_ms' => 0, 'end_ms' => 5000,
            'hook_score' => 10, 'score_rationale' => 'stale', 'status' => ClipCandidate::STATUS_PENDING,
        ]);

        $this->fakeOllama(['highlights' => [
            ['start_ms' => 0, 'end_ms' => 25000, 'hook_score' => 60, 'rationale' => 'fresh'],
        ]]);

        $this->runJob($video);

        // Approved survived; stale pending gone; fresh pending added.
        $this->assertDatabaseHas('clip_candidates', ['id' => $approved->id, 'status' => 'approved']);
        $this->assertDatabaseMissing('clip_candidates', ['score_rationale' => 'stale']);
        $this->assertDatabaseHas('clip_candidates', ['score_rationale' => 'fresh', 'status' => 'pending']);
    }

    public function test_bad_batch_is_skipped_without_failing_the_video(): void
    {
        $video = $this->makeVideoWithTranscript();
        // Unusable output — schema throws internally, job logs and continues.
        $this->fakeOllama('garbage-not-an-array');

        $this->runJob($video);

        // No candidates, but the video moved to reviewing (not failed).
        $this->assertSame(0, ClipCandidate::where('video_id', $video->id)->count());
        $this->assertSame('reviewing', $video->fresh()->status);
    }

    public function test_video_with_no_transcript_is_a_noop(): void
    {
        $video = Video::create([
            'source_type' => 'upload', 'source_ref' => 'x.mp4', 'status' => 'transcribed',
            'duration_seconds' => 60, 'storage_path' => 'videos/y/source.mp4',
        ]);
        $this->fakeOllama(['highlights' => []]);

        $this->runJob($video);

        $this->assertSame(0, ClipCandidate::count());
        $this->assertSame('reviewing', $video->fresh()->status);
    }

    public function test_failed_hook_marks_video_and_pipeline_failed(): void
    {
        $video = $this->makeVideoWithTranscript();

        (new ScoreHighlightsJob($video->id))->failed(new RuntimeException('ollama down'));

        $this->assertSame('failed', $video->fresh()->status);
        $this->assertDatabaseHas('pipeline_jobs', [
            'video_id' => $video->id, 'stage' => 'score',
            'status' => 'failed', 'last_error' => 'ollama down',
        ]);
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }
}
