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
        int $panX,
        int $renderW,
        int $renderH,
        string $layout = 'single',
    ): array {
        $startSec = $this->msToSec($clip['start_ms']);
        $durSec = $this->msToSec($clip['end_ms'] - $clip['start_ms']);

        $assFilterPath = $this->escapeForFilter($assPath);

        if ($layout === 'split_gaming') {
            $faceH = (int) round($renderH * 0.4);
            $gameH = $renderH - $faceH;

            // Split gaming layout:
            // 1. Crop face region: square of size in_h * 9/16 centered at $panX horizontally, scale to renderW x faceH
            // 2. Crop gameplay region: center 16:9 of original video, scale to renderW x gameH
            // 3. Stack them vertically
            // 4. Overlay subtitles
            $filter = sprintf(
                '[0:v]crop=in_h*9/16:in_h*9/16:%d:0,scale=%d:%d[face]; ' .
                '[0:v]crop=in_h*16/9:in_h:(in_w-in_h*16/9)/2:0,scale=%d:%d[game]; ' .
                '[face][game]vstack=inputs=2[stacked]; ' .
                '[stacked]subtitles=%s[out]',
                $panX,
                $renderW,
                $faceH,
                $renderW,
                $gameH,
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
        $filter = sprintf(
            'crop=%d:%d:%d:0,scale=%d:%d,subtitles=%s',
            $crop['width'],
            $crop['height'],
            $panX,
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
