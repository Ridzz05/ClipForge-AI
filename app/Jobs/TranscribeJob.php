<?php

namespace App\Jobs;

use App\Models\PipelineJob;
use App\Models\Transcript;
use App\Models\Video;
use App\Services\WhisperService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stage 2 (spec section 5.2). Transcribes a video via the faster-whisper
 * service and persists word-level segments.
 *
 * Crash safety (DoD: "job queue survives a worker crash mid-stage — retry, not
 * data loss"):
 *  - The write is wrapped in a DB transaction, so a crash mid-persist leaves no
 *    partial transcript.
 *  - It is idempotent: any prior transcript for the video is deleted first, so a
 *    retry after a partial run reproduces a clean result instead of duplicating.
 *  - failed() records the terminal error on the video + pipeline row for review.
 */
class TranscribeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $videoId,
    ) {}

    /** Hard timeout mirrors the transcribe budget (resource exhaustion guard). */
    public function timeout(): int
    {
        return (int) config('autoclip.timeouts.transcribe');
    }

    public function handle(WhisperService $whisper): void
    {
        $video = Video::find($this->videoId);
        if (! $video) {
            return; // Video deleted (e.g. retention cleanup) — nothing to do.
        }

        $pipelineJob = $this->markRunning($video);

        $absolutePath = Storage::disk((string) config('autoclip.ingest.disk'))
            ->path($video->storage_path);

        // Network/CPU-heavy call happens OUTSIDE the transaction so we don't
        // hold a write lock on SQLite for the whole transcription.
        $result = $whisper->transcribe($absolutePath);

        DB::transaction(function () use ($video, $result) {
            // Idempotent: drop any prior transcript (cascade clears segments).
            Transcript::where('video_id', $video->id)->delete();

            $transcript = Transcript::create([
                'video_id' => $video->id,
                'full_text' => $result['full_text'],
                'language' => $result['language'],
            ]);

            $rows = array_map(fn (array $seg) => [
                'transcript_id' => $transcript->id,
                'start_ms' => $seg['start_ms'],
                'end_ms' => $seg['end_ms'],
                'text' => $seg['text'],
                'speaker_label' => null,
                'words' => json_encode($seg['words']),
                'created_at' => now(),
                'updated_at' => now(),
            ], $result['segments']);

            foreach (array_chunk($rows, 500) as $chunk) {
                DB::table('transcript_segments')->insert($chunk);
            }

            $video->update(['status' => 'transcribed']);
        });

        $pipelineJob->update(['status' => 'done', 'last_error' => null]);

        // Hand off to Stage 3 (Score highlights). Guarded so this stage stays
        // green even before ScoreJob exists.
        if (class_exists(\App\Jobs\ScoreHighlightsJob::class)) {
            \App\Jobs\ScoreHighlightsJob::dispatch($video->id);
        }
    }

    private function markRunning(Video $video): PipelineJob
    {
        $video->update(['status' => 'transcribing']);

        $job = PipelineJob::firstOrNew([
            'video_id' => $video->id,
            'stage' => 'transcribe',
        ]);
        $job->status = 'running';
        $job->attempts = ($job->attempts ?? 0) + 1;
        $job->save();

        return $job;
    }

    /** Terminal failure after all retries — persist the error for review. */
    public function failed(Throwable $e): void
    {
        $video = Video::find($this->videoId);
        $video?->update(['status' => 'failed']);

        // Upsert: the running row normally exists (handle ran first), but be
        // robust if failure happened before it was created.
        $job = PipelineJob::firstOrNew([
            'video_id' => $this->videoId,
            'stage' => 'transcribe',
        ]);
        $job->status = 'failed';
        $job->last_error = $e->getMessage();
        $job->save();
    }
}
