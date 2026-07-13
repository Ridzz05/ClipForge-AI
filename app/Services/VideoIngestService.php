<?php

namespace App\Services;

use App\Exceptions\IngestValidationException;
use App\Jobs\TranscribeJob;
use App\Models\PipelineJob;
use App\Models\Video;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Stage 1 (spec section 5.1). Validates an uploaded video by magic bytes
 * (not extension), enforces size/duration caps, and stores it under a
 * job-scoped path with a server-generated filename — the user never controls
 * any path component (spec section 6: command injection / path traversal).
 */
class VideoIngestService
{
    public function __construct(
        private readonly FfprobeService $ffprobe,
    ) {}

    /**
     * @return Video the persisted, validated video row
     *
     * @throws IngestValidationException on any validation failure
     */
    public function ingestUpload(UploadedFile $file): Video
    {
        $this->assertSizeWithinCap($file);
        $this->assertAllowedMime($file);

        // Server-generated identity. The original filename is retained only as
        // an informational label, never used to build a path.
        $videoUuid = (string) Str::uuid();
        $extension = $this->safeExtensionForMime($file);
        $relativePath = "videos/{$videoUuid}/source.{$extension}";

        $disk = $this->disk();
        // storeAs writes the stream to our path; the original client name is
        // discarded entirely.
        $disk->putFileAs("videos/{$videoUuid}", $file, "source.{$extension}");

        $absolutePath = $disk->path($relativePath);

        // Duration cap — probed only after the file is safely on disk under a
        // path we control.
        try {
            $duration = $this->ffprobe->durationSeconds($absolutePath);
        } catch (\RuntimeException $e) {
            $disk->deleteDirectory("videos/{$videoUuid}");
            throw new IngestValidationException(
                'Uploaded file is not a valid, decodable video.',
                previous: $e,
            );
        }

        if ($duration === null) {
            $disk->deleteDirectory("videos/{$videoUuid}");
            throw new IngestValidationException('Could not determine video duration.');
        }

        $maxDuration = (int) config('autoclip.ingest.max_duration_seconds');
        if ($duration > $maxDuration) {
            $disk->deleteDirectory("videos/{$videoUuid}");
            throw new IngestValidationException(
                "Video is {$duration}s long; the maximum is {$maxDuration}s."
            );
        }

        $video = Video::create([
            'source_type' => 'upload',
            'source_ref' => $file->getClientOriginalName(),
            'status' => 'ingested',
            'duration_seconds' => $duration,
            'storage_path' => $relativePath,
        ]);

        PipelineJob::create([
            'video_id' => $video->id,
            'stage' => 'ingest',
            'status' => 'done',
            'attempts' => 1,
        ]);

        // Kick off Stage 2 asynchronously so ingest returns immediately.
        TranscribeJob::dispatch($video->id);

        return $video;
    }

    /**
     * Create the videos row for a URL ingest UP FRONT, before the (slow, async)
     * download — so the operator immediately sees a "downloading" video and any
     * later failure is visible on that row instead of vanishing into a log.
     */
    public function createPendingUrlVideo(string $sourceUrl): Video
    {
        $video = Video::create([
            'source_type' => 'url',
            'source_ref' => $sourceUrl,
            'status' => 'downloading',
            'storage_path' => null,
        ]);

        PipelineJob::create([
            'video_id' => $video->id,
            'stage' => 'ingest',
            'status' => 'queued',
            'attempts' => 0,
        ]);

        return $video;
    }

    /**
     * Finalise a URL ingest after yt-dlp has produced $relativePath under the
     * video's job directory. Validates duration (same cap as uploads), then
     * marks the video ingested and starts the pipeline. On failure the caller
     * (IngestUrlJob) marks the video failed; here we clean up the file and throw.
     *
     * @throws IngestValidationException on validation failure (cleans up the dir)
     */
    public function completeUrlIngest(Video $video, string $relativePath, string $jobDir): Video
    {
        $disk = $this->disk();
        $absolutePath = $disk->path($relativePath);

        try {
            $duration = $this->ffprobe->durationSeconds($absolutePath);
        } catch (\RuntimeException $e) {
            $disk->deleteDirectory($jobDir);
            throw new IngestValidationException(
                'Downloaded file is not a valid, decodable video.',
                previous: $e,
            );
        }

        if ($duration === null) {
            $disk->deleteDirectory($jobDir);
            throw new IngestValidationException('Could not determine video duration.');
        }

        $maxDuration = (int) config('autoclip.ingest.max_duration_seconds');
        if ($duration > $maxDuration) {
            $disk->deleteDirectory($jobDir);
            throw new IngestValidationException(
                "Video is {$duration}s long; the maximum is {$maxDuration}s."
            );
        }

        $video->update([
            'status' => 'ingested',
            'duration_seconds' => $duration,
            'storage_path' => $relativePath,
            'last_error' => null,
        ]);

        PipelineJob::updateOrCreate(
            ['video_id' => $video->id, 'stage' => 'ingest'],
            ['status' => 'done', 'last_error' => null],
        );

        TranscribeJob::dispatch($video->id);

        return $video;
    }

    private function assertSizeWithinCap(UploadedFile $file): void
    {
        $maxKb = (int) config('autoclip.ingest.max_size_kb');
        if ($file->getSize() > $maxKb * 1024) {
            throw new IngestValidationException('Uploaded file exceeds the size limit.');
        }
    }

    /**
     * Magic-byte validation: getMimeType() reads the file content via finfo,
     * NOT the client-supplied extension or Content-Type header.
     */
    private function assertAllowedMime(UploadedFile $file): void
    {
        $allowed = (array) config('autoclip.ingest.allowed_mimes');
        $detected = $file->getMimeType(); // content-based

        if (! in_array($detected, $allowed, true)) {
            throw new IngestValidationException(
                "Unsupported file type ({$detected}). Allowed: ".implode(', ', $allowed)
            );
        }
    }

    private function safeExtensionForMime(UploadedFile $file): string
    {
        // Extension derived from detected MIME, never from the client name.
        return match ($file->getMimeType()) {
            'video/mp4', 'application/mp4' => 'mp4',
            'video/quicktime' => 'mov',
            'video/x-matroska' => 'mkv',
            'video/webm' => 'webm',
            'video/x-msvideo' => 'avi',
            default => 'bin',
        };
    }

    private function disk(): Filesystem
    {
        return Storage::disk((string) config('autoclip.ingest.disk'));
    }
}
