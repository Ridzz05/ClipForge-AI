<?php

namespace App\Services\Reframe;

/**
 * Builds the ffmpeg argument array for overlaying a watermark PNG onto a
 * rendered clip (spec section 5.5). Separate + pure so the filter assembly is
 * unit-testable and every argument stays an explicit array element (spec
 * section 6: no shell string interpolation). Reuses the overlay approach
 * validated for the Yeet Casino campaign tooling.
 */
class WatermarkCommandBuilder
{
    /**
     * Overlay $watermarkPath onto $inputPath, writing $outputPath. Positioned
     * bottom-right with a margin; the video stream is re-encoded, audio copied.
     *
     * @param  int  $margin  px inset from the bottom-right corner
     * @return array<int, string> ffmpeg args (without the binary)
     */
    public function build(
        string $inputPath,
        string $watermarkPath,
        string $outputPath,
        int $margin = 40,
    ): array {
        // Two inputs (clip + watermark); overlay the second on the first.
        // overlay=W-w-M:H-h-M => bottom-right with M px margin (W/H = main,
        // w/h = overlay). Values are numeric/inert — no user data.
        $filter = sprintf('overlay=W-w-%d:H-h-%d', $margin, $margin);

        return [
            '-y',
            '-i', $inputPath,
            '-i', $watermarkPath,
            '-filter_complex', $filter,
            '-c:a', 'copy',
            '-c:v', 'libx264',
            '-preset', 'veryfast',
            '-movflags', '+faststart',
            $outputPath,
        ];
    }
}
