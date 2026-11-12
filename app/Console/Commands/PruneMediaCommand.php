<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Models\Export;
use App\Models\Video;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

/**
 * Manual retention: removes failed videos (and their files) older than N days.
 * Dry-run unless --force. Intentionally not scheduled.
 */
class PruneMediaCommand extends Command
{
    protected $signature = 'autoclip:prune
        {--days= : Minimum age in days (default: autoclip.retention_days)}
        {--force : Actually delete; without it only report}';

    protected $description = 'Delete failed videos and their stored media older than the retention window';

    public function handle(): int
    {
        $days = (int) ($this->option('days') ?: config('autoclip.retention_days', 30));
        $cutoff = now()->subDays($days);

        $videos = Video::where('status', 'failed')
            ->where('updated_at', '<', $cutoff)
            ->get();

        $n = $videos->count();

        if (! $this->option('force')) {
            $this->info("Would delete {$n} videos older than {$days} days. Re-run with --force to delete.");

            return self::SUCCESS;
        }

        $ingestDisk = Storage::disk((string) config('autoclip.ingest.disk'));
        $exportDisk = Storage::disk((string) config('autoclip.render.disk'));

        foreach ($videos as $video) {
            $exportIds = Export::whereIn(
                'clip_candidate_id',
                $video->clipCandidates()->select('id'),
            )->pluck('id');

            foreach ($exportIds as $exportId) {
                $exportDisk->deleteDirectory("exports/{$exportId}");
            }

            $dir = $video->storage_path !== null ? dirname($video->storage_path) : '';
            if ($dir === '' || $dir === '.' || $dir === 'videos') {
                $dir = "videos/url-{$video->id}";
            }
            $ingestDisk->deleteDirectory($dir);

            $video->delete();
        }

        $this->info("Deleted {$n} videos.");

        return self::SUCCESS;
    }
}
