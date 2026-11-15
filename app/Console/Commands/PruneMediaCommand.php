<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Services\MediaCleanupService;
use Illuminate\Console\Command;

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

    public function handle(MediaCleanupService $cleanup): int
    {
        $days = (int) ($this->option('days') ?: config('autoclip.retention_days', 30));
        $videos = $cleanup->staleFailedVideos($days);

        $n = $videos->count();

        if (! $this->option('force')) {
            $this->info("Would delete {$n} videos older than {$days} days. Re-run with --force to delete.");

            return self::SUCCESS;
        }

        foreach ($videos as $video) {
            $cleanup->deleteVideo($video);
        }

        $this->info("Deleted {$n} videos.");

        return self::SUCCESS;
    }
}
