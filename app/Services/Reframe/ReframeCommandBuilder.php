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
    ): array {
        $startSec = $this->msToSec($clip['start_ms']);
        $durSec = $this->msToSec($clip['end_ms'] - $clip['start_ms']);

        // Filter graph:
        //   crop the 9:16 window at panX → scale to render size → burn subtitles.
        // ASS path is escaped for the filter-arg mini-language (colons/backslashes
        // on Windows), but the file path itself is server-generated, never user data.
        $assFilterPath = $this->escapeForFilter($assPath);

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
