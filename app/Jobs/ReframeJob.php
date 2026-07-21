<?php

namespace App\Jobs;

use App\Models\Export;
use App\Models\PipelineJob;
use App\Models\TranscriptSegment;
use App\Services\FfprobeService;
use App\Services\Reframe\CaptionRenderer;
use App\Services\Reframe\FaceTrackingService;
use App\Services\Reframe\FfmpegService;
use App\Services\Reframe\ReframeCommandBuilder;
use App\Services\Reframe\ReframePlanner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Stage 4 (spec section 5.4). For one approved clip candidate: reframe to 9:16
 * tracking the speaker, burn in word-level captions, and write the clip.
 * Stage 5 (watermark) is layered on top of the produced file.
 *
 * All ffmpeg invocation goes through FfmpegService argument arrays — no shell
 * strings, and every path is server-generated (spec section 6).
 */
class ReframeJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public readonly int $exportId,
    ) {}

    public function timeout(): int
    {
        return (int) config('autoclip.timeouts.reframe') + 120;
    }

    public function handle(
        FfprobeService $ffprobe,
        FaceTrackingService $faces,
        ReframePlanner $planner,
        CaptionRenderer $captions,
        ReframeCommandBuilder $builder,
        FfmpegService $ffmpeg,
    ): void {
        $export = Export::with('clipCandidate.video')->find($this->exportId);
        if (! $export) {
            return;
        }

        $candidate = $export->clipCandidate;
        $video = $candidate->video;

        $pipelineJob = $this->markRunning($export, $video->id);

        Log::info('Reframe: start', ['export_id' => $export->id, 'video_id' => $video->id]);

        $inputDisk = Storage::disk((string) config('autoclip.ingest.disk'));
        $inputPath = $inputDisk->path($video->storage_path);

        $renderW = (int) config('autoclip.render.width');
        $renderH = (int) config('autoclip.render.height');

        // 1. Source dimensions + crop plan.
        $dims = $ffprobe->dimensions($inputPath);
        $crop = $planner->cropSize($dims['width'], $dims['height']);

        // 2. Face centres → smoothed pan or manual crop position.
        if ($export->manual_crop_x !== null) {
            $cropW = $crop['width'];
            $maxX = max(0, $dims['width'] - $cropW);
            $desiredX = ($export->manual_crop_x * $dims['width']) - ($cropW / 2);
            $panX = (int) round(max(0.0, min((float) $maxX, $desiredX)));
            Log::info('Reframe: using manual crop X', ['manual_crop_x' => $export->manual_crop_x, 'computed_pan_x' => $panX]);
        } else {
            $centers = $faces->sampleCenters($inputPath, $candidate->start_ms, $candidate->end_ms);
            $panPath = $planner->panPath($dims['width'], $dims['height'], $centers);
            if (count($panPath) > 1) {
                $panX = $planner->buildCropXExpression($panPath, $candidate->start_ms);
                Log::info('Reframe: using dynamic camera pan path', ['keyframes' => count($panPath)]);
            } else {
                $panX = $this->medianX($panPath);
            }
        }

        $exportDisk = Storage::disk((string) config('autoclip.render.disk'));
        $ctaText = $export->cta_text ?? (string) config('autoclip.render.cta_text', '');
        $exportDisk->makeDirectory("exports/{$export->id}");
        $outputRelative = "exports/{$export->id}/".Str::uuid().'.mp4';
        $outputPath = $exportDisk->path($outputRelative);

        $segments = $export->segments;
        if (is_array($segments) && count($segments) > 1) {
            Log::info('Reframe: processing multi-segment jump-cut rendering', ['export_id' => $export->id, 'segments_count' => count($segments)]);
            $concatLines = [];

            foreach ($segments as $sIdx => $seg) {
                $segStartMs = (int) $seg['start_ms'];
                $segEndMs = (int) $seg['end_ms'];
                $segDurMs = max(500, $segEndMs - $segStartMs);

                $segAssRelative = "exports/{$export->id}/captions_seg_{$sIdx}.ass";
                $exportDisk->put($segAssRelative, $captions->renderAss(
                    $this->wordsForClip($video->id, $segStartMs, $segEndMs),
                    $export->caption_style,
                    $renderW,
                    $renderH,
                    $ctaText,
                    $segDurMs,
                    $export->caption_margin_v,
                    $export->caption_font
                ));
                $segAssPath = $exportDisk->path($segAssRelative);

                $segOutputRelative = "exports/{$export->id}/seg_{$sIdx}.mp4";
                $segOutputPath = $exportDisk->path($segOutputRelative);

                $segArgs = $builder->build(
                    inputPath: $inputPath,
                    assPath: $segAssPath,
                    outputPath: $segOutputPath,
                    clip: ['start_ms' => $segStartMs, 'end_ms' => $segEndMs],
                    crop: $crop,
                    panX: $panX,
                    renderW: $renderW,
                    renderH: $renderH,
                    layout: $export->layout ?? 'single',
                    splitTopCropX: $export->split_top_crop_x,
                    splitBottomCropX: $export->split_bottom_crop_x,
                );
                $ffmpeg->run($segArgs);

                $concatLines[] = "file '".$segOutputPath."'";
            }

            $concatRelative = "exports/{$export->id}/concat.txt";
            $exportDisk->put($concatRelative, implode("\n", $concatLines));
            $concatPath = $exportDisk->path($concatRelative);

            $concatArgs = [
                '-y',
                '-f', 'concat',
                '-safe', '0',
                '-i', $concatPath,
                '-c', 'copy',
                $outputPath,
            ];
            $ffmpeg->run($concatArgs);
        } else {
            // Single segment normal build
            $assRelative = "exports/{$export->id}/captions.ass";
            $clipDurationMs = $candidate->end_ms - $candidate->start_ms;
            $exportDisk->put($assRelative, $captions->renderAss(
                $this->wordsForClip($video->id, $candidate->start_ms, $candidate->end_ms),
                $export->caption_style,
                $renderW,
                $renderH,
                $ctaText,
                $clipDurationMs,
                $export->caption_margin_v,
                $export->caption_font
            ));
            $assPath = $exportDisk->path($assRelative);

            $args = $builder->build(
                inputPath: $inputPath,
                assPath: $assPath,
                outputPath: $outputPath,
                clip: ['start_ms' => $candidate->start_ms, 'end_ms' => $candidate->end_ms],
                crop: $crop,
                panX: $panX,
                renderW: $renderW,
                renderH: $renderH,
                layout: $export->layout ?? 'single',
                splitTopCropX: $export->split_top_crop_x,
                splitBottomCropX: $export->split_bottom_crop_x,
            );
            $ffmpeg->run($args);
        }

        $export->update([
            'output_path' => $outputRelative,
            'status' => Export::STATUS_RENDERED,
            'last_error' => null,
            'rendered_at' => now(),
        ]);
        $pipelineJob->update(['status' => 'done', 'last_error' => null]);

        Log::info('Reframe: done', ['export_id' => $export->id, 'output' => $outputRelative]);

        // Hand off to Stage 5 (watermark + deliver) if present.
        if (class_exists(\App\Jobs\ExportDeliverJob::class)) {
            \App\Jobs\ExportDeliverJob::dispatch($export->id);
        }
    }

    /**
     * @return array<int, array{word:string, start_ms:int, end_ms:int}>
     */
    private function wordsForClip(int $videoId, int $startMs, int $endMs): array
    {
        $segments = TranscriptSegment::whereHas('transcript',
            fn ($q) => $q->where('video_id', $videoId))
            ->where('end_ms', '>', $startMs)
            ->where('start_ms', '<', $endMs)
            ->orderBy('start_ms')
            ->get();

        $words = [];
        foreach ($segments as $seg) {
            foreach ($seg->words ?? [] as $w) {
                if (! isset($w['word'], $w['start_ms'], $w['end_ms'])) {
                    continue;
                }
                // Keep words overlapping the clip; rebase to clip start.
                if ($w['end_ms'] <= $startMs || $w['start_ms'] >= $endMs) {
                    continue;
                }
                $words[] = [
                    'word' => (string) $w['word'],
                    'start_ms' => max(0, (int) $w['start_ms'] - $startMs),
                    'end_ms' => max(0, (int) $w['end_ms'] - $startMs),
                ];
            }
        }

        return $words;
    }

    /**
     * @param  array<int, array{t_ms:int, x:int}>  $panPath
     */
    private function medianX(array $panPath): int
    {
        if ($panPath === []) {
            return 0;
        }
        $xs = array_column($panPath, 'x');
        sort($xs);

        return (int) $xs[intdiv(count($xs), 2)];
    }

    private function markRunning(Export $export, int $videoId): PipelineJob
    {
        $export->update(['status' => Export::STATUS_RENDERING]);

        $job = PipelineJob::firstOrNew([
            'video_id' => $videoId,
            'stage' => 'reframe',
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
            // Upsert: the running row normally exists (handle ran first), but be
            // robust if failure happened before it was created.
            $job = PipelineJob::firstOrNew([
                'video_id' => $export->clipCandidate->video_id,
                'stage' => 'reframe',
            ]);
            $job->status = 'failed';
            $job->last_error = $e->getMessage();
            $job->save();
        }
    }
}
