<?php

namespace App\Jobs;

use App\Models\ClipCandidate;
use App\Models\Export;
use App\Models\PipelineJob;
use App\Services\Reframe\FfmpegService;
use App\Services\Reframe\WatermarkCommandBuilder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stage 5 (spec section 5.5). Takes the reframed/captioned clip from Stage 4,
 * applies the watermark overlay (if configured), and marks the export ready
 * for manual download (Phase 1: no bot auto-notify yet).
 *
 * All ffmpeg via FfmpegService argument arrays; all paths server-generated
 * (spec section 6). Idempotent + crash-safe: a retry re-derives the watermarked
 * file from the reframed source and overwrites cleanly.
 */
class ExportDeliverJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $exportId,
    ) {}

    public function timeout(): int
    {
        return (int) config('autoclip.timeouts.export');
    }

    public function handle(
        WatermarkCommandBuilder $builder,
        FfmpegService $ffmpeg,
    ): void {
        $export = Export::with('clipCandidate')->find($this->exportId);
        if (! $export || $export->output_path === null) {
            return; // nothing rendered to deliver
        }

        $pipelineJob = $this->markRunning($export);

        $disk = Storage::disk((string) config('autoclip.render.disk'));
        $watermarkPath = config('autoclip.render.watermark_path');

        if (is_string($watermarkPath) && $watermarkPath !== '' && is_file($watermarkPath)) {
            $oldRelative = $export->output_path;
            $reframedAbs = $disk->path($oldRelative);
            $finalRelative = "exports/{$export->id}/".Str::uuid().'-final.mp4';
            $finalAbs = $disk->path($finalRelative);

            $ffmpeg->run($builder->build($reframedAbs, $watermarkPath, $finalAbs));

            $export->update([
                'output_path' => $finalRelative,
                'watermark_applied' => true,
            ]);

            // The un-watermarked intermediate is no longer needed.
            $disk->delete($oldRelative);
        }

        // Mark the candidate exported (terminal happy state for Phase 1).
        $export->clipCandidate?->update(['status' => ClipCandidate::STATUS_EXPORTED]);
        $export->update(['status' => Export::STATUS_RENDERED, 'rendered_at' => now()]);
        $pipelineJob->update(['status' => 'done', 'last_error' => null]);

        Log::info('Export: delivered', [
            'export_id' => $export->id,
            'watermark' => $export->watermark_applied,
        ]);
    }

    private function relativeOf(string $absolute, string $diskRoot): string
    {
        return ltrim(str_replace('\\', '/', substr($absolute, strlen($diskRoot))), '/');
    }

    private function markRunning(Export $export): PipelineJob
    {
        $job = PipelineJob::firstOrNew([
            'video_id' => $export->clipCandidate->video_id,
            'stage' => 'export',
        ]);
        $job->status = 'running';
        $job->attempts = ($job->attempts ?? 0) + 1;
        $job->save();

        return $job;
    }

    public function failed(Throwable $e): void
    {
        $export = Export::with('clipCandidate')->find($this->exportId);
        $export?->update([
            'status' => Export::STATUS_FAILED,
            'last_error' => $e->getMessage(),
        ]);

        if ($export) {
            $job = PipelineJob::firstOrNew([
                'video_id' => $export->clipCandidate->video_id,
                'stage' => 'export',
            ]);
            $job->status = 'failed';
            $job->last_error = $e->getMessage();
            $job->save();
        }
    }
}
