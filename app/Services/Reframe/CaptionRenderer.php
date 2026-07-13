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
        'default' => [
            'Fontname' => 'Arial',
            'Fontsize' => 16,
            'PrimaryColour' => '&H00FFFFFF', // white
            'OutlineColour' => '&H00000000', // black outline
            'Bold' => 1,
            'Alignment' => 2,  // bottom-center
            'MarginV' => 60,
        ],
        'karaoke_yellow' => [
            'Fontname' => 'Arial',
            'Fontsize' => 18,
            'PrimaryColour' => '&H0000FFFF', // yellow
            'OutlineColour' => '&H00000000',
            'Bold' => 1,
            'Alignment' => 2,
            'MarginV' => 80,
        ],
    ];

    /**
     * Render an ASS document for the given words, whose timestamps are already
     * relative to the clip start (0 = clip start).
     *
     * @param  array<int, array{word:string, start_ms:int, end_ms:int}>  $words
     * @param  int  $playResX  render width (px)
     * @param  int  $playResY  render height (px)
     */
    public function renderAss(array $words, string $style, int $playResX, int $playResY): string
    {
        $tpl = $this->templates[$style] ?? $this->templates['default'];

        $header = $this->header($tpl, $playResX, $playResY);
        $events = $this->events($words);

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
            2, 1,                // Outline, Shadow
            $tpl['Alignment'],
            10, 10,              // MarginL, MarginR
            $tpl['MarginV'],
            1,                   // Encoding
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

        [Events]
        Format: Layer, Start, End, Style, Name, MarginL, MarginR, MarginV, Effect, Text
        ASS;
    }

    /**
     * @param  array<int, array{word:string, start_ms:int, end_ms:int}>  $words
     */
    private function events(array $words): string
    {
        $lines = [];
        foreach ($words as $w) {
            $start = $this->toAssTime((int) $w['start_ms']);
            $end = $this->toAssTime((int) $w['end_ms']);
            $text = $this->sanitize((string) $w['word']);
            if ($text === '') {
                continue;
            }
            $lines[] = "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$text}";
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
