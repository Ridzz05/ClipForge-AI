<?php

namespace App\Services\Reframe;

/**
 * Builds the ffmpeg argument array for reframing one clip to 9:16 with burned
 * captions. Kept separate from the job so the (tricky) filter-graph assembly is
 * unit-testable without running ffmpeg, and so every argument stays an explicit
 * array element (spec section 6: no shell string interpolation).
 */
class ReframeCommandBuilder
{
    /**
     * @param  array{start_ms:int, end_ms:int}  $clip  absolute source range
     * @param  array{width:int, height:int}  $crop  crop window size
     * @param  int  $panX  horizontal top-left of the crop window (static per clip)
     * @return array<int, string> ffmpeg args (without the binary itself)
     */
    public function build(
        string $inputPath,
        string $assPath,
        string $outputPath,
        array $clip,
        array $crop,
        int|string $panX,
        int $renderW,
        int $renderH,
        string $layout = 'single',
        ?float $splitTopCropX = 0.25,
        ?float $splitBottomCropX = 0.75,
    ): array {
        $startSec = $this->msToSec($clip['start_ms']);
        $durSec = $this->msToSec($clip['end_ms'] - $clip['start_ms']);

        $assFilterPath = $this->escapeForFilter($assPath);

        if ($layout === 'split_podcast') {
            $halfH = (int) round($renderH / 2);

            // Compute crop offsets: topRatio and botRatio (0.0 to 0.5)
            $topRatio = max(0.0, min(0.5, ($splitTopCropX ?? 0.25) - 0.25));
            $botRatio = max(0.0, min(0.5, ($splitBottomCropX ?? 0.75) - 0.25));

            $filter = sprintf(
                '[0:v]crop=in_w*0.5:in_h:in_w*%.4f:0,scale=%d:%d,setsar=1[top_spk]; ' .
                '[0:v]crop=in_w*0.5:in_h:in_w*%.4f:0,scale=%d:%d,setsar=1[bot_spk]; ' .
                '[top_spk][bot_spk]vstack=inputs=2[stacked]; ' .
                '[stacked]subtitles=%s[out]',
                $topRatio,
                $renderW,
                $halfH,
                $botRatio,
                $renderW,
                $halfH,
                $assFilterPath
            );

            return [
                '-y',
                '-ss', $startSec,
                '-i', $inputPath,
                '-t', $durSec,
                '-filter_complex', $filter,
                '-map', '[out]',
                '-map', '0:a',
                '-c:a', 'aac',
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-movflags', '+faststart',
                $outputPath,
            ];
        }

        if ($layout === 'split_gaming') {
            $gameH = (int) round($renderH * 0.6);
            $faceH = $renderH - $gameH;

            // Split gaming layout (Gameplay top, Facecam/Reaction bottom):
            // 1. Crop gameplay region: top-half of the screen (width 100%, height 50%), scale to renderW x gameH
            // 2. Crop face region: bottom-left corner of the video (width 35%, height 50%), scale to renderW x faceH
            // 3. Stack them vertically: gameplay on top [game], reaction on bottom [face]
            // 4. Overlay subtitles
            $filter = sprintf(
                '[0:v]crop=in_w:in_h*0.5:0:0,scale=%d:%d,setsar=1[game]; ' .
                '[0:v]crop=in_w*0.35:in_h*0.5:0:in_h*0.5,scale=%d:%d,setsar=1[face]; ' .
                '[game][face]vstack=inputs=2[stacked]; ' .
                '[stacked]subtitles=%s[out]',
                $renderW,
                $gameH,
                $renderW,
                $faceH,
                $assFilterPath
            );

            return [
                '-y',
                '-ss', $startSec,
                '-i', $inputPath,
                '-t', $durSec,
                '-filter_complex', $filter,
                '-map', '[out]',
                '-map', '0:a',
                '-c:a', 'aac',
                '-c:v', 'libx264',
                '-preset', 'veryfast',
                '-movflags', '+faststart',
                $outputPath,
            ];
        }

        // Single speaker vertical crop (default)
        $panExpr = is_numeric($panX) ? (string) $panX : "'".str_replace("'", "", (string) $panX)."'";
        $filter = sprintf(
            'crop=%d:%d:%s:0,scale=%d:%d,subtitles=%s',
            $crop['width'],
            $crop['height'],
            $panExpr,
            $renderW,
            $renderH,
            $assFilterPath,
        );

        return [
            '-y',
            '-ss', $startSec,
            '-i', $inputPath,
            '-t', $durSec,
            '-vf', $filter,
            '-c:a', 'aac',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-movflags', '+faststart',
            $outputPath,
        ];
    }

    private function msToSec(int $ms): string
    {
        return number_format(max(0, $ms) / 1000, 3, '.', '');
    }

    /**
     * Escape a path for use inside an ffmpeg filter argument. Only the filter
     * mini-language needs this (backslash, colon, quotes) — the value is still a
     * single arg element, so this is about filter parsing, not shell safety.
     */
    private function escapeForFilter(string $path): string
    {
        $path = str_replace('\\', '/', $path);      // normalise Windows separators
        $path = str_replace(':', '\\:', $path);      // escape drive-letter colon
        $path = str_replace("'", "\\'", $path);

        return "'".$path."'";
    }
}
