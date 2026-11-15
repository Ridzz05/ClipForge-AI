<?php

namespace App\Services\Reframe;

/**
 * Pure geometry for Stage 4 (spec section 5.4): computes a vertical 9:16 crop
 * window over a landscape source and a smoothed horizontal pan path that keeps
 * the tracked subject centred.
 *
 * No I/O, no ffmpeg — just math, so it is fully unit-testable. The ReframeJob
 * turns the resulting keyframes into ffmpeg crop expressions.
 */
class ReframePlanner
{
    /** Default output aspect: 9:16 (portrait). */
    public const DEFAULT_RATIO = '9:16';

    /**
     * Supported output ratios → render size in px (width, height).
     *
     * @var array<string, array{0:int, 1:int}>
     */
    public const RATIOS = [
        '9:16' => [1080, 1920],
        '1:1' => [1080, 1080],
        '4:5' => [1080, 1350],
        '16:9' => [1920, 1080],
    ];

    /**
     * Exponential-smoothing factor for the pan path (0..1). Lower = smoother /
     * lazier camera; higher = snappier tracking. Keeps the crop from jittering
     * frame-to-frame with noisy face detections.
     */
    public const SMOOTHING = 0.15;

    /**
     * Render size (px) for an output ratio; unknown ratios fall back to 9:16.
     *
     * @return array{width:int, height:int}
     */
    public static function renderSize(string $ratio): array
    {
        [$w, $h] = self::RATIOS[$ratio] ?? self::RATIOS[self::DEFAULT_RATIO];

        return ['width' => $w, 'height' => $h];
    }

    /**
     * Compute the crop width/height for the output ratio inside the source.
     *
     * @return array{width:int, height:int}
     */
    public function cropSize(int $srcWidth, int $srcHeight, string $ratio = self::DEFAULT_RATIO): array
    {
        $out = self::renderSize($ratio);

        // Height-driven: take full height, derive the width for the ratio.
        $cropH = $srcHeight;
        $cropW = (int) round($srcHeight * $out['width'] / $out['height']);

        if ($cropW <= $srcWidth) {
            return ['width' => $cropW, 'height' => $cropH];
        }

        // Source is narrower than the ratio: width-driven.
        $cropW = $srcWidth;
        $cropH = (int) round($srcWidth * $out['height'] / $out['width']);

        return ['width' => $cropW, 'height' => min($cropH, $srcHeight)];
    }

    /**
     * Build a pan path: for each sample, the top-left x of the crop window,
     * clamped so the window never leaves the frame, and exponentially smoothed.
     *
     * @param  array<int, array{t_ms:int, cx:float}>  $faceCenters  normalized cx in 0..1
     * @return array<int, array{t_ms:int, x:int}>
     */
    public function panPath(int $srcWidth, int $srcHeight, array $faceCenters, string $ratio = self::DEFAULT_RATIO): array
    {
        $cropW = $this->cropSize($srcWidth, $srcHeight, $ratio)['width'];
        $maxX = max(0, $srcWidth - $cropW);

        // No detections → static centre crop (spec: fallback).
        if ($faceCenters === []) {
            return [['t_ms' => 0, 'x' => intdiv($maxX, 2)]];
        }

        // Sort by time so smoothing runs forward.
        usort($faceCenters, fn ($a, $b) => $a['t_ms'] <=> $b['t_ms']);

        $path = [];
        $smoothed = null;
        foreach ($faceCenters as $sample) {
            $cx = max(0.0, min(1.0, $sample['cx']));
            // Desired top-left so the face centre sits at the crop centre.
            $desiredX = ($cx * $srcWidth) - ($cropW / 2);
            $desiredX = max(0.0, min((float) $maxX, $desiredX));

            $smoothed = $smoothed === null
                ? $desiredX
                : $smoothed + self::SMOOTHING * ($desiredX - $smoothed);

            $path[] = ['t_ms' => $sample['t_ms'], 'x' => (int) round($smoothed)];
        }

        return $path;
    }

    /**
     * Convert timestamped pan keyframes into an FFmpeg time-dependent expression
     * string for dynamic camera panning (e.g. crop=w:h:x='expr':y).
     *
     * @param  array<int, array{t_ms:int, x:int}>  $panPath
     */
    public function buildCropXExpression(array $panPath, int $clipStartMs = 0): string
    {
        if ($panPath === []) {
            return '0';
        }

        if (count($panPath) === 1) {
            return (string) $panPath[0]['x'];
        }

        $last = end($panPath);
        $expr = (string) $last['x'];

        for ($i = count($panPath) - 2; $i >= 0; $i--) {
            $curr = $panPath[$i];
            $next = $panPath[$i + 1];

            $t1 = max(0.0, ($curr['t_ms'] - $clipStartMs) / 1000.0);
            $t2 = max(0.0, ($next['t_ms'] - $clipStartMs) / 1000.0);
            $x1 = $curr['x'];
            $x2 = $next['x'];

            if ($t2 <= $t1) {
                continue;
            }

            $formattedT1 = number_format($t1, 3, '.', '');
            $dx = $x2 - $x1;
            $dt = number_format($t2 - $t1, 3, '.', '');

            if ($dx === 0) {
                $piece = (string) $x1;
            } else {
                $piece = sprintf('%d+(%d)*(t-%s)/%s', $x1, $dx, $formattedT1, $dt);
            }

            $expr = sprintf('if(lte(t,%s),%s,%s)', $formattedT1, $piece, $expr);
        }

        return $expr;
    }

    /**
     * Whether the source is already tall enough that no horizontal pan is
     * meaningful (crop spans full width).
     */
    public function isAlreadyVertical(int $srcWidth, int $srcHeight, string $ratio = self::DEFAULT_RATIO): bool
    {
        return $this->cropSize($srcWidth, $srcHeight, $ratio)['width'] >= $srcWidth;
    }
}
