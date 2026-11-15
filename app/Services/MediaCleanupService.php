<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Export;
use App\Models\Video;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * Single owner of "what files belong to a video" — measuring disk usage,
 * deleting a video with its media, and sweeping leftover render intermediates
 * (seg_*.mp4, captions*.ass, concat.txt, pre-mix/pre-final files).
 */
class MediaCleanupService
{
    /** Exports in these states may still be writing intermediates; never touch them. */
    private const SETTLED = [Export::STATUS_RENDERED, Export::STATUS_FAILED];

    /** Failed videos not updated for $days days. */
    public function staleFailedVideos(int $days): Collection
    {
        return Video::where('status', 'failed')
            ->where('updated_at', '<', now()->subDays($days))
            ->get();
    }

    /** Delete the video's source dir, every export dir and the DB rows (FKs cascade). */
    public function deleteVideo(Video $video): void
    {
        $exportDisk = $this->exportDisk();
        foreach ($this->exports($video) as $export) {
            $exportDisk->deleteDirectory("exports/{$export->id}");
        }

        $this->ingestDisk()->deleteDirectory($this->sourceDir($video));
        $video->delete();
    }

    /**
     * @return array{
     *     videos: list<array{id:int,title:string,status:string,source_bytes:int,export_bytes:int,reclaimable_bytes:int}>,
     *     total_bytes: int,
     *     reclaimable_bytes: int
     * }
     */
    public function usage(): array
    {
        $rows = [];
        $total = 0;
        $reclaimable = 0;

        foreach (Video::orderByDesc('id')->get() as $video) {
            $sourceBytes = $this->dirBytes($this->ingestDisk(), $this->sourceDir($video));
            $exportBytes = 0;
            $reclaim = 0;
            foreach ($this->exports($video) as $export) {
                $exportBytes += $this->dirBytes($this->exportDisk(), "exports/{$export->id}");
                foreach ($this->workFiles($export) as $path) {
                    $reclaim += $this->exportDisk()->size($path);
                }
            }

            $rows[] = [
                'id' => $video->id,
                'title' => (string) $video->source_ref,
                'status' => (string) $video->status,
                'source_bytes' => $sourceBytes,
                'export_bytes' => $exportBytes,
                'reclaimable_bytes' => $reclaim,
            ];
            $total += $sourceBytes + $exportBytes;
            $reclaimable += $reclaim;
        }

        return ['videos' => $rows, 'total_bytes' => $total, 'reclaimable_bytes' => $reclaimable];
    }

    /** Delete leftover intermediates of finished exports. Returns bytes freed. */
    public function purgeWorkFiles(): int
    {
        $freed = 0;
        $disk = $this->exportDisk();

        foreach (Export::whereIn('status', self::SETTLED)->get() as $export) {
            foreach ($this->workFiles($export) as $path) {
                $freed += $disk->size($path);
                $disk->delete($path);
            }
        }

        return $freed;
    }

    /**
     * Files in the export dir that are not the deliverable. Empty while the
     * export may still be rendering.
     *
     * @return list<string>
     */
    private function workFiles(Export $export): array
    {
        if (! in_array($export->status, self::SETTLED, true)) {
            return [];
        }

        return array_values(array_filter(
            $this->exportDisk()->files("exports/{$export->id}"),
            fn (string $path): bool => $path !== $export->output_path,
        ));
    }

    /** @return Collection<int, Export> */
    private function exports(Video $video): Collection
    {
        return Export::whereIn('clip_candidate_id', $video->clipCandidates()->select('id'))
            ->get(['id', 'output_path', 'status']);
    }

    private function sourceDir(Video $video): string
    {
        $dir = $video->storage_path !== null ? dirname($video->storage_path) : '';
        if ($dir === '' || $dir === '.' || $dir === 'videos') {
            return "videos/url-{$video->id}";
        }

        return $dir;
    }

    private function dirBytes(Filesystem $disk, string $dir): int
    {
        $bytes = 0;
        foreach ($disk->allFiles($dir) as $path) {
            $bytes += $disk->size($path);
        }

        return $bytes;
    }

    private function ingestDisk(): Filesystem
    {
        return Storage::disk((string) config('autoclip.ingest.disk'));
    }

    private function exportDisk(): Filesystem
    {
        return Storage::disk((string) config('autoclip.render.disk'));
    }
}
