<?php

namespace App\Jobs;

use App\Exceptions\IngestValidationException;
use App\Models\PipelineJob;
use App\Models\Video;
use App\Services\VideoIngestService;
use App\Services\YtDlpService;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Stage 1 via URL (spec 5.1). The videos row already exists (status=downloading,
 * created synchronously so the operator sees it immediately); this job downloads
 * the URL with yt-dlp into that video's job directory and finalises ingest.
 *
 * Visibility: every outcome is recorded on the video + pipeline_jobs, never just
 * a log line — a failed download shows up as a failed video with a reason.
 */
class IngestUrlJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $backoff = 30;

    public function __construct(
        public readonly int $videoId,
    ) {}

    public function timeout(): int
    {
        return (int) config('autoclip.url_ingest.timeout') + 120;
    }

    public function handle(YtDlpService $ytdlp, VideoIngestService $ingest): void
    {
        $video = Video::find($this->videoId);
        if (! $video) {
            return; // row deleted; nothing to do
        }

        $video->update(['status' => 'downloading']);
        $this->markIngest($video, 'running');

        // Fail fast on an unsafe URL before spawning yt-dlp.
        $ytdlp->assertSafeUrl($video->source_ref);

        // Job dir keyed by video id keeps it server-generated and predictable.
        $jobDir = "videos/url-{$video->id}";
        $disk = Storage::disk((string) config('autoclip.ingest.disk'));
        $absDir = $disk->path($jobDir);

        Log::info('URL ingest: downloading', ['video_id' => $video->id, 'url' => $video->source_ref]);

        $downloadedAbs = $ytdlp->download($video->source_ref, $absDir, 'source');
        $relative = $jobDir.'/'.basename($downloadedAbs);

        $ingest->completeUrlIngest($video, $relative, $jobDir);

        Log::info('URL ingest: complete', [
            'video_id' => $video->id,
            'duration_seconds' => $video->fresh()?->duration_seconds,
        ]);
    }

    private function markIngest(Video $video, string $status, ?string $error = null): void
    {
        $job = PipelineJob::firstOrNew(['video_id' => $video->id, 'stage' => 'ingest']);
        $job->status = $status;
        if ($status === 'running') {
            $job->attempts = ($job->attempts ?? 0) + 1;
        }
        $job->last_error = $error;
        $job->save();
    }

    /**
     * Any failure (bad URL, download error, validation reject) is recorded ON
     * the video so it's visible in the UI — not swallowed into a log file.
     */
    public function failed(Throwable $e): void
    {
        $message = $e instanceof IngestValidationException
            ? $e->getMessage()
            : 'Download failed: '.$e->getMessage();

        $video = Video::find($this->videoId);
        $video?->update(['status' => 'failed', 'last_error' => $message]);

        if ($video) {
            $this->markIngest($video, 'failed', $message);
        }

        Log::error('URL ingest failed', [
            'video_id' => $this->videoId,
            'error' => $message,
        ]);
    }
}
