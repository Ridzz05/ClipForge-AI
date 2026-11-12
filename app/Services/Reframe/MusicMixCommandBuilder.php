<?php

namespace App\Services\Reframe;

/**
 * Mixes a looped background track under a rendered clip's original audio.
 * Video stream is copied untouched; only audio is re-encoded. Argument array
 * only (spec section 6): volume is a validated integer, paths are server-made.
 */
class MusicMixCommandBuilder
{
    /**
     * @param  int  $volumePercent  music level relative to full scale (0-100)
     * @return array<int, string> ffmpeg args (without the binary)
     */
    public function build(string $clipPath, string $musicPath, string $outputPath, int $volumePercent): array
    {
        $vol = max(0, min(100, $volumePercent)) / 100;

        // Music loops (-stream_loop -1) and is cut to the clip length by
        // amix duration=first; the clip's own audio stays at full level.
        $filter = sprintf(
            '[1:a]volume=%.2f[bg];[0:a][bg]amix=inputs=2:duration=first:dropout_transition=0:normalize=0[aout]',
            $vol
        );

        return [
            '-y',
            '-i', $clipPath,
            '-stream_loop', '-1',
            '-i', $musicPath,
            '-filter_complex', $filter,
            '-map', '0:v',
            '-map', '[aout]',
            '-c:v', 'copy',
            '-c:a', 'aac',
            '-shortest',
            '-movflags', '+faststart',
            $outputPath,
        ];
    }
}
