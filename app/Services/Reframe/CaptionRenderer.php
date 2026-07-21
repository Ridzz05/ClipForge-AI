<?php

namespace App\Services\Reframe;

/**
 * Builds an ASS subtitle document from word-level timestamps (spec section 5.4:
 * "burn in captions from word-level timestamps ... caption style should be a
 * configurable template, not hardcoded").
 *
 * Output is written to a .ass file which ffmpeg burns in via the `subtitles`
 * filter. Pure string generation — no I/O here — so it is unit-testable and
 * the ffmpeg side only deals with a file path (argument-array safe).
 */
class CaptionRenderer
{
    /**
     * Named style templates. A caller passes a style key; unknown keys fall
     * back to 'default'. Values map to ASS V4+ style fields.
     *
     * @var array<string, array<string, string|int>>
     */
    private array $templates = [
        'none' => [
            'Fontname' => 'Arial',
            'Fontsize' => 10,
            'PrimaryColour' => '&H00FFFFFF',
            'OutlineColour' => '&H00000000',
            'Bold' => 0,
            'Alignment' => 2,
            'MarginV' => 0,
            'Outline' => 0,
            'Shadow' => 0,
            'Mode' => 'none',
        ],
        'default' => [
            'Fontname' => 'Arial',
            'Fontsize' => 64,
            'PrimaryColour' => '&H00FFFFFF', // white
            'OutlineColour' => '&H00000000', // black outline
            'Bold' => 1,
            'Alignment' => 2,  // bottom-center
            'MarginV' => 240,
            'Outline' => 4,
            'Shadow' => 1,
            'Mode' => 'karaoke',
            'HighlightColour' => '&H0000FFFF', // yellow highlight
        ],
        'karaoke_yellow' => [
            'Fontname' => 'Arial',
            'Fontsize' => 72,
            'PrimaryColour' => '&H00FFFFFF', // white base
            'OutlineColour' => '&H00000000',
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 280,
            'Outline' => 5,
            'Shadow' => 1,
            'Mode' => 'karaoke',
            'HighlightColour' => '&H0000FFFF', // yellow highlight
        ],
        'tiktok_green' => [
            'Fontname' => 'Arial Black',
            'Fontsize' => 76,
            'PrimaryColour' => '&H0000FF00', // lime green
            'OutlineColour' => '&H00000000',
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 320,
            'Outline' => 6,
            'Shadow' => 2,
            'Mode' => 'pop', // zoom bounce pop animation
        ],
        'short_bold' => [
            'Fontname' => 'Impact',
            'Fontsize' => 84,
            'PrimaryColour' => '&H00FFFFFF', // white
            'OutlineColour' => '&H00000000',
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 360,
            'Outline' => 6,
            'Shadow' => 2,
            'Mode' => 'pop', // zoom bounce pop animation
        ],
        'hormozi_neon' => [
            'Fontname' => 'Segoe UI Black', // Very heavy sans-serif standard on Windows
            'Fontsize' => 76,
            'PrimaryColour' => '&H00FFFFFF', // White base
            'OutlineColour' => '&H00000000', // Black outline
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 300,
            'Outline' => 5,
            'Shadow' => 2,
            'Mode' => 'pop',
            'MultiColor' => true,
            'Uppercase' => true,
        ],
        'mrbeast_comic' => [
            'Fontname' => 'Impact',
            'Fontsize' => 84,
            'PrimaryColour' => '&H0000FFFF', // Neon yellow base
            'OutlineColour' => '&H00000000', // Heavy black outline
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 340,
            'Outline' => 7,
            'Shadow' => 1,
            'Mode' => 'pop',
            'Uppercase' => true,
        ],
        'minimal_outfit' => [
            'Fontname' => 'Trebuchet MS', // Smooth rounded modern font
            'Fontsize' => 64,
            'PrimaryColour' => '&H00FFFFFF',
            'OutlineColour' => '&H00000000',
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 240,
            'Outline' => 3,
            'Shadow' => 0,
            'Mode' => 'karaoke',
            'HighlightColour' => '&H0000FFFF',
            'Uppercase' => false,
        ],
    ];

    /**
     * Render an ASS document for the given words, whose timestamps are already
     * relative to the clip start (0 = clip start).
     *
     * @param  array<int, array{word:string, start_ms:int, end_ms:int}>  $words
     * @param  int  $playResX  render width (px)
     * @param  int  $playResY  render height (px)
     * @param  string  $ctaText  optional fixed CTA burned at the top for the whole clip
     * @param  int  $clipDurationMs  clip length, used for the CTA's end time
     * @param  int|null  $customMarginV  optional custom vertical margin
     */
    public function renderAss(
        array $words,
        string $style,
        int $playResX,
        int $playResY,
        string $ctaText = '',
        int $clipDurationMs = 0,
        ?int $customMarginV = null,
        ?string $customFontName = null
    ): string {
        $tpl = $this->templates[$style] ?? $this->templates['default'];
        if ($customMarginV !== null) {
            $tpl['MarginV'] = $customMarginV;
        }

        if ($customFontName !== null && trim($customFontName) !== '') {
            $tpl['Fontname'] = trim($customFontName);
        }

        $header = $this->header($tpl, $playResX, $playResY);
        $events = $this->events($words, $tpl);

        $cta = $this->ctaEvent($ctaText, $clipDurationMs);
        if ($cta !== '') {
            $events = $events === '' ? $cta : $events."\n".$cta;
        }

        return $header."\n".$events."\n";
    }

    public function availableStyles(): array
    {
        return array_keys($this->templates);
    }

    /**
     * @param  array<string, string|int>  $tpl
     */
    private function header(array $tpl, int $playResX, int $playResY): string
    {
        $outline = $tpl['Outline'] ?? 4;
        $shadow = $tpl['Shadow'] ?? 1;

        $style = implode(',', [
            'Default',
            $tpl['Fontname'],
            $tpl['Fontsize'],
            $tpl['PrimaryColour'],
            '&H000000FF',        // SecondaryColour
            $tpl['OutlineColour'],
            '&H64000000',        // BackColour (semi-transparent)
            $tpl['Bold'],
            0, 0, 0,             // Italic, Underline, StrikeOut
            100, 100,            // ScaleX, ScaleY
            0, 0,                // Spacing, Angle
            1,                   // BorderStyle (outline+shadow)
            $outline,
            $shadow,             // Outline, Shadow
            $tpl['Alignment'],
            10, 10,              // MarginL, MarginR
            $tpl['MarginV'],
            1,                   // Encoding
        ]);

        // Fixed CTA style: top-centre (align 8), bold, larger, high-contrast —
        // makes the "game is OUT" call-to-action unmissable (campaign rule).
        $ctaStyle = implode(',', [
            'CTA',
            $tpl['Fontname'],
            (int) $tpl['Fontsize'] + 4,
            '&H00FFFFFF',        // white
            '&H000000FF',
            '&H00000000',        // black outline
            '&H64000000',
            1,                   // Bold
            0, 0, 0,
            100, 100,
            0, 0,
            1,
            3, 1,                // thicker outline
            8,                   // top-centre
            10, 10,
            50,                  // MarginV from top
            1,
        ]);

        return <<<ASS
        [Script Info]
        ScriptType: v4.00+
        PlayResX: {$playResX}
        PlayResY: {$playResY}
        WrapStyle: 2

        [V4+ Styles]
        Format: Name, Fontname, Fontsize, PrimaryColour, SecondaryColour, OutlineColour, BackColour, Bold, Italic, Underline, StrikeOut, ScaleX, ScaleY, Spacing, Angle, BorderStyle, Outline, Shadow, Alignment, MarginL, MarginR, MarginV, Encoding
        Style: {$style}
        Style: {$ctaStyle}

        [Events]
        Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
        ASS;
    }

    /**
     * A single Dialogue line for the fixed CTA, spanning the whole clip. Returns
     * '' when there is no CTA text. The text is sanitised like captions, so a
     * hostile CTA can't inject ASS override tags (spec section 6).
     */
    private function ctaEvent(string $ctaText, int $clipDurationMs): string
    {
        $text = $this->sanitize($ctaText);
        if ($text === '') {
            return '';
        }

        $end = $this->toAssTime(max(1, $clipDurationMs));

        return "Dialogue: 0,{$this->toAssTime(0)},{$end},CTA,,0,0,0,,{$text}";
    }

    /**
     * @param  array<int, array{word:string, start_ms:int, end_ms:int}>  $words
     * @param  array<string, string|int>  $tpl
     */
    private function events(array $words, array $tpl): string
    {
        $mode = $tpl['Mode'] ?? 'pop';
        if ($mode === 'none') {
            return '';
        }

        $isUppercase = (bool)($tpl['Uppercase'] ?? false);
        $lines = [];

        if ($mode === 'pop') {
            $isMultiColor = (bool)($tpl['MultiColor'] ?? false);
            // Colors: White, White, Neon Green, White, Neon Yellow
            $multiColors = ['&H00FFFFFF', '&H00FFFFFF', '&H0000FF00', '&H00FFFFFF', '&H0000FFFF'];

            // One word at a time with zoom bounce animation
            foreach ($words as $idx => $w) {
                $start = $this->toAssTime((int) $w['start_ms']);
                $end = $this->toAssTime((int) $w['end_ms']);
                $text = $this->sanitize((string) $w['word']);
                if ($text === '') {
                    continue;
                }

                if ($isUppercase) {
                    $text = mb_strtoupper($text);
                }

                $colorOverride = '';
                if ($isMultiColor) {
                    $color = $multiColors[$idx % count($multiColors)];
                    $colorOverride = "\\c{$color}";
                }

                // Zoom bounce: fscx130\fscy130 at start, transition to 100% over 100ms
                $animatedText = "{\\fscx130\\fscy130\\t(0,100,\\fscx100\\fscy100){$colorOverride}}" . $text;
                $lines[] = "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$animatedText}";
            }
        } else {
            // Karaoke Phrase Style: group words into phrases of 3 words
            $chunks = array_chunk($words, 3);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $index => $activeWord) {
                    $wordStart = (int) $activeWord['start_ms'];
                    $wordEnd = (int) $activeWord['end_ms'];

                    $lineWords = [];
                    foreach ($chunk as $i => $w) {
                        $cleanWord = $this->sanitize($w['word']);
                        if ($cleanWord === '') {
                            continue;
                        }

                        if ($isUppercase) {
                            $cleanWord = mb_strtoupper($cleanWord);
                        }

                        if ($i === $index) {
                            $highlight = $tpl['HighlightColour'] ?? '&H0000FFFF';
                            $primary = $tpl['PrimaryColour'] ?? '&H00FFFFFF';
                            // Format: {\c<Highlight>}word{\c<Primary>}
                            $lineWords[] = "{\\c{$highlight}}" . $cleanWord . "{\\c{$primary}}";
                        } else {
                            $lineWords[] = $cleanWord;
                        }
                    }

                    $text = implode(' ', $lineWords);
                    if (trim($text) === '') {
                        continue;
                    }

                    $startStr = $this->toAssTime($wordStart);
                    $endStr = $this->toAssTime($wordEnd);
                    $lines[] = "Dialogue: 0,{$startStr},{$endStr},Default,,0,0,0,,{$text}";
                }
            }
        }

        return implode("\n", $lines);
    }

    /** ASS time format: H:MM:SS.cs (centiseconds). */
    private function toAssTime(int $ms): string
    {
        $ms = max(0, $ms);
        $cs = intdiv($ms, 10) % 100;
        $totalSec = intdiv($ms, 1000);
        $s = $totalSec % 60;
        $m = intdiv($totalSec, 60) % 60;
        $h = intdiv($totalSec, 3600);

        return sprintf('%d:%02d:%02d.%02d', $h, $m, $s, $cs);
    }

    /**
     * Neutralise ASS control characters so caption text (which originates from
     * the transcript — untrusted, spec section 6) can't inject override tags or
     * break out of the Dialogue line.
     */
    private function sanitize(string $text): string
    {
        $text = str_replace(["\r", "\n"], ' ', $text);
        // Braces start ASS override blocks ({\...}); strip them entirely.
        $text = str_replace(['{', '}'], '', $text);

        return trim($text);
    }
}
