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

        // 2. Face centres → smoothed pan; static median X keeps the crop stable.
        $centers = $faces->sampleCenters($inputPath, $candidate->start_ms, $candidate->end_ms);
        $panPath = $planner->panPath($dims['width'], $dims['height'], $centers);
        $panX = $this->medianX($panPath);

        // 3. Captions for this clip, timestamps rebased to clip start, plus a
        //    fixed on-screen CTA (campaign requirement) spanning the whole clip.
        $assRelative = "exports/{$export->id}/captions.ass";
        $exportDisk = Storage::disk((string) config('autoclip.render.disk'));
        $clipDurationMs = $candidate->end_ms - $candidate->start_ms;
        $ctaText = $export->cta_text ?? (string) config('autoclip.render.cta_text', '');
        $exportDisk->put($assRelative, $captions->renderAss(
            $this->wordsForClip($video->id, $candidate->start_ms, $candidate->end_ms),
            $export->caption_style,
            $renderW,
            $renderH,
            $ctaText,
            $clipDurationMs,
        ));
        $assPath = $exportDisk->path($assRelative);

        // 4. Output path — server-generated, never user data.
        $outputRelative = "exports/{$export->id}/".Str::uuid().'.mp4';
        $exportDisk->makeDirectory("exports/{$export->id}");
        $outputPath = $exportDisk->path($outputRelative);

        // 5. Build + run the ffmpeg command (argument array).
        $args = $builder->build(
            inputPath: $inputPath,
            assPath: $assPath,
            outputPath: $outputPath,
            clip: ['start_ms' => $candidate->start_ms, 'end_ms' => $candidate->end_ms],
            crop: $crop,
            panX: $panX,
            renderW: $renderW,
            renderH: $renderH,
        );
        $ffmpeg->run($args);

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
