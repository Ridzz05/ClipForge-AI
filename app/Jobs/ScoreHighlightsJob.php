<?php

namespace App\Jobs;

use App\Models\ClipCandidate;
use App\Models\PipelineJob;
use App\Models\Transcript;
use App\Models\Video;
use App\Services\Scoring\HighlightSchema;
use App\Services\Scoring\InvalidHighlightSchemaException;
use App\Services\Scoring\OllamaService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Stage 3 (spec section 5.3). Scores highlight candidates from a video's
 * transcript using the LLM, validates the output against a strict schema, and
 * persists candidates in `pending` state for human review.
 *
 * Security: the transcript is untrusted (spec section 6). LLM output is passed
 * through HighlightSchema and only ever stored as bounded data — never used to
 * build paths or commands.
 *
 * Crash safety: idempotent (drops prior pending candidates first) and
 * transactional, so a retry after a mid-run crash reproduces a clean set
 * without wiping candidates a human already approved/exported.
 */
class ScoreHighlightsJob implements ShouldQueue
{
    use Queueable;

    /** Segments per LLM call — batched, never the whole transcript at once. */
    public const BATCH_SIZE = 40;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $videoId,
    ) {}

    public function timeout(): int
    {
        return (int) config('autoclip.timeouts.score') + 120;
    }

    public function handle(OllamaService $ollama, HighlightSchema $schema): void
    {
        $video = Video::find($this->videoId);
        if (! $video) {
            return;
        }

        $transcript = Transcript::where('video_id', $video->id)
            ->with('segments')->first();
        if (! $transcript || $transcript->segments->isEmpty()) {
            // Nothing to score — not an error, just an empty result.
            $this->markReviewing($video);

            $job = PipelineJob::firstOrNew([
                'video_id' => $video->id,
                'stage' => 'score',
            ]);
            $job->status = 'done';
            $job->attempts = ($job->attempts ?? 0) + 1;
            $job->save();

            return;
        }

        $pipelineJob = $this->markRunning($video);
        $durationMs = (int) (($video->duration_seconds ?? 0) * 1000);

        Log::info('Score: start', [
            'video_id' => $video->id,
            'segments' => $transcript->segments->count(),
        ]);

        $candidates = [];
        foreach ($transcript->segments->chunk(self::BATCH_SIZE) as $batch) {
            $payload = $batch->map(fn ($s) => [
                'start_ms' => $s->start_ms,
                'end_ms' => $s->end_ms,
                'text' => $s->text,
            ])->values()->all();

            try {
                $raw = $ollama->scoreBatch($payload);
            } catch (\Exception $e) {
                Log::warning('Batch scoring failed, skipping chunk.', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            try {
                $validated = $schema->validate($raw, $durationMs);
            } catch (InvalidHighlightSchemaException $e) {
                // One bad batch shouldn't sink the whole video — log and skip.
                Log::warning('Highlight schema rejected a batch', [
                    'video_id' => $video->id,
                    'error' => $e->getMessage(),
                ]);

                continue;
            }

            foreach ($validated as $v) {
                $candidates[] = $v;
            }

            // Sleep for 3 seconds between batches to avoid WAF/gateway rate limit triggers
            sleep(3);
        }

        $this->persist($video, $candidates);
        $pipelineJob->update(['status' => 'done', 'last_error' => null]);
        $this->markReviewing($video);

        Log::info('Score: done', [
            'video_id' => $video->id,
            'candidates' => count($candidates),
        ]);
    }

    /**
     * @param  array<int, array{start_ms:int,end_ms:int,hook_score:int,rationale:string}>  $candidates
     */
    private function persist(Video $video, array $candidates): void
    {
        DB::transaction(function () use ($video, $candidates) {
            // Idempotent: clear only not-yet-reviewed candidates so a retry
            // doesn't wipe approvals/exports a human already acted on.
            ClipCandidate::where('video_id', $video->id)
                ->where('status', ClipCandidate::STATUS_PENDING)
                ->delete();

            $now = now();
            $rows = array_map(fn (array $c) => [
                'video_id' => $video->id,
                'start_ms' => $c['start_ms'],
                'end_ms' => $c['end_ms'],
                'hook_score' => $c['hook_score'],
                'score_rationale' => $c['rationale'],
                'status' => ClipCandidate::STATUS_PENDING,
                'created_at' => $now,
                'updated_at' => $now,
            ], $candidates);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('clip_candidates')->insert($chunk);
            }
        });
    }

    private function markRunning(Video $video): PipelineJob
    {
        $video->update(['status' => 'scoring']);

        $job = PipelineJob::firstOrNew([
            'video_id' => $video->id,
            'stage' => 'score',
        ]);
        $job->status = 'running';
        $job->attempts = ($job->attempts ?? 0) + 1;
        $job->save();

        return $job;
    }

    /** Candidates now await human review before any export (review gate). */
    private function markReviewing(Video $video): void
    {
        $video->update(['status' => 'reviewing']);
    }

    public function failed(Throwable $e): void
    {
        Video::find($this->videoId)?->update(['status' => 'failed']);

        $job = PipelineJob::firstOrNew([
            'video_id' => $this->videoId,
            'stage' => 'score',
        ]);
        $job->status = 'failed';
        $job->last_error = $e->getMessage();
        $job->save();
    }
}
