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
    /** Entrance animations a caption line can use (null = template default). */
    public const ANIMATIONS = ['pop', 'fade', 'rise', 'drop', 'zoom', 'blur', 'type', 'off'];

    /** Text effects layered over the template style (null = template default). */
    public const EFFECTS = ['none', 'outline', 'glow', 'shadow', 'box'];

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
     * @param  string|null  $animation  one of self::ANIMATIONS; null keeps the template's own motion
     * @param  string|null  $effect  one of self::EFFECTS; null keeps the template's own outline/shadow
     */
    public function renderAss(
        array $words,
        string $style,
        int $playResX,
        int $playResY,
        string $ctaText = '',
        int $clipDurationMs = 0,
        ?int $customMarginV = null,
        ?string $customFontName = null,
        ?string $headlineText = null,
        ?string $headlineStyle = null,
        ?string $animation = null,
        ?string $effect = null
    ): string {
        $tpl = $this->templates[$style] ?? $this->templates['default'];
        if ($customMarginV !== null) {
            $tpl['MarginV'] = $customMarginV;
        }

        if ($customFontName !== null && trim($customFontName) !== '') {
            $tpl['Fontname'] = trim($customFontName);
        }

        $tpl = $this->applyEffect($tpl, $effect);

        $header = $this->header($tpl, $playResX, $playResY, $headlineStyle);
        $events = $this->events($words, $tpl, in_array($animation, self::ANIMATIONS, true) ? $animation : null, $playResX, $playResY);

        $cta = $this->ctaEvent($ctaText, $clipDurationMs);
        if ($cta !== '') {
            $events = $events === '' ? $cta : $events."\n".$cta;
        }

        $headline = $this->headlineEvent((string) $headlineText, $clipDurationMs);
        if ($headline !== '') {
            $events = $events === '' ? $headline : $events."\n".$headline;
        }

        return $header."\n".$events."\n";
    }

    public function availableStyles(): array
    {
        return array_keys($this->templates);
    }

    /**
     * Auto Headline looks (ASS colours are BGR). Box styles use BorderStyle 3,
     * where OutlineColour paints the opaque box behind the text.
     *
     * @return array<string, array{label:string, primary:string, outline:string, borderStyle:int, outlineSize:int}>
     */
    public static function headlineStyles(): array
    {
        return [
            'marker' => ['label' => 'Spidol', 'primary' => '&H00FFFFFF', 'outline' => '&H00000000', 'borderStyle' => 1, 'outlineSize' => 5],
            'highlight' => ['label' => 'Stabilo', 'primary' => '&H00000000', 'outline' => '&H0000FFFF', 'borderStyle' => 3, 'outlineSize' => 12],
            'sticker' => ['label' => 'Stiker', 'primary' => '&H00FFFFFF', 'outline' => '&H002828D1', 'borderStyle' => 3, 'outlineSize' => 12],
        ];
    }

    /**
     * @param  array<string, string|int>  $tpl
     */
    private function header(array $tpl, int $playResX, int $playResY, ?string $headlineStyle = null): string
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
            $tpl['BackColour'] ?? '&H64000000', // BackColour (semi-transparent by default)
            $tpl['Bold'],
            0, 0, 0,             // Italic, Underline, StrikeOut
            100, 100,            // ScaleX, ScaleY
            0, 0,                // Spacing, Angle
            $tpl['BorderStyle'] ?? 1, // BorderStyle (1 outline+shadow, 3 opaque box)
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

        // Auto Headline: top-centre, below the CTA line, large bold.
        $hs = self::headlineStyles()[$headlineStyle ?? ''] ?? self::headlineStyles()['marker'];
        $headlineLine = implode(',', [
            'Headline',
            $tpl['Fontname'],
            (int) $tpl['Fontsize'] + 8,
            $hs['primary'],
            '&H000000FF',
            $hs['outline'],
            '&H64000000',
            1,
            0, 0, 0,
            100, 100,
            0, 0,
            $hs['borderStyle'],
            $hs['outlineSize'], 0,
            8,
            60, 60,
            180,
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
        Style: {$headlineLine}

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
     * Headline for the first seconds of the clip (hook). Returns '' when empty.
     * Sanitised like captions so it can't inject ASS override tags.
     */
    private function headlineEvent(string $text, int $clipDurationMs): string
    {
        $text = $this->sanitize($text);
        if ($text === '') {
            return '';
        }

        $end = $this->toAssTime(max(1, min($clipDurationMs, 5000)));

        return "Dialogue: 1,{$this->toAssTime(0)},{$end},Headline,,0,0,0,,{$text}";
    }

    /**
     * Overlay a text effect on the template. Unknown/null effects leave it as is.
     *
     * @param  array<string, string|int>  $tpl
     * @return array<string, string|int>
     */
    private function applyEffect(array $tpl, ?string $effect): array
    {
        switch ($effect) {
            case 'none':
                $tpl['Outline'] = 0;
                $tpl['Shadow'] = 0;
                break;
            case 'outline':
                $tpl['Outline'] = 5;
                $tpl['Shadow'] = 0;
                $tpl['OutlineColour'] = '&H00000000';
                break;
            case 'glow':
                // Soft neon halo: coloured outline + blur on the line.
                $tpl['Outline'] = 6;
                $tpl['Shadow'] = 0;
                $tpl['OutlineColour'] = '&H00FFCC00';
                $tpl['LineTags'] = '\\blur5';
                $tpl['BlurBase'] = 5;
                break;
            case 'shadow':
                $tpl['Outline'] = 0;
                $tpl['Shadow'] = 7;
                $tpl['BackColour'] = '&H00000000';
                break;
            case 'box':
                // BorderStyle 3: OutlineColour paints the opaque box behind the text.
                $tpl['BorderStyle'] = 3;
                $tpl['Outline'] = 10;
                $tpl['Shadow'] = 0;
                $tpl['OutlineColour'] = '&H00000000';
                break;
        }

        return $tpl;
    }

    /**
     * ASS override tags (no braces) for a line's entrance animation.
     *
     * @param  array<string, string|int>  $tpl
     */
    private function entranceTags(string $animation, array $tpl, int $playResX, int $playResY): string
    {
        $x = intdiv($playResX, 2);
        $y = $playResY - (int) ($tpl['MarginV'] ?? 0);
        $blurBase = (int) ($tpl['BlurBase'] ?? 0);

        return match ($animation) {
            'pop' => '\\fscx130\\fscy130\\t(0,100,\\fscx100\\fscy100)',
            'fade' => '\\fad(250,0)',
            'rise' => '\\an2\\move('.$x.','.($y + 60).','.$x.','.$y.',0,200)\\fad(120,0)',
            'drop' => '\\an2\\move('.$x.','.($y - 60).','.$x.','.$y.',0,200)\\fad(120,0)',
            'zoom' => '\\fscx60\\fscy60\\t(0,220,\\fscx100\\fscy100)\\fad(120,0)',
            'blur' => '\\blur14\\t(0,260,\\blur'.$blurBase.')\\fad(200,0)',
            default => '',
        };
    }

    /**
     * Typewriter reveal: every character starts fully transparent and is
     * switched on $stepMs after the previous one. $cursorMs carries across
     * words so a phrase types out continuously.
     *
     * @param  array<string, string|int>  $tpl
     */
    private function typed(string $text, int &$cursorMs, int $stepMs, array $tpl): string
    {
        $shadowAlpha = strtoupper(substr((string) ($tpl['BackColour'] ?? '&H64000000'), 2, 2));
        $out = '';
        foreach (mb_str_split($text) as $ch) {
            $on = $cursorMs;
            $out .= '{\\1a&HFF&\\3a&HFF&\\4a&HFF&\\t('.$on.','.($on + 1).',\\1a&H00&\\3a&H00&\\4a&H'.$shadowAlpha.'&)}'.$ch;
            $cursorMs += $stepMs;
        }

        return $out;
    }

    private function tagBlock(string $tags): string
    {
        return $tags === '' ? '' : '{'.$tags.'}';
    }

    /**
     * @param  array<int, array{word:string, start_ms:int, end_ms:int}>  $words
     * @param  array<string, string|int>  $tpl
     */
    private function events(array $words, array $tpl, ?string $animation, int $playResX, int $playResY): string
    {
        $mode = $tpl['Mode'] ?? 'pop';
        if ($mode === 'none') {
            return '';
        }

        // Template default: pop templates bounce, karaoke templates stay still.
        $anim = $animation ?? ($mode === 'pop' ? 'pop' : 'off');
        $entrance = $this->entranceTags($anim, $tpl, $playResX, $playResY);
        $lineTags = (string) ($tpl['LineTags'] ?? '');

        $isUppercase = (bool)($tpl['Uppercase'] ?? false);
        $lines = [];

        if ($mode === 'pop') {
            $isMultiColor = (bool)($tpl['MultiColor'] ?? false);
            // Colors: White, White, Neon Green, White, Neon Yellow
            $multiColors = ['&H00FFFFFF', '&H00FFFFFF', '&H0000FF00', '&H00FFFFFF', '&H0000FFFF'];

            // One word at a time, each with the entrance animation
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

                $body = $text;
                if ($anim === 'type') {
                    $durMs = max(1, (int) $w['end_ms'] - (int) $w['start_ms']);
                    $step = max(15, min(45, intdiv((int) ($durMs * 0.7), max(1, mb_strlen($text)))));
                    $cursor = 0;
                    $body = $this->typed($text, $cursor, $step, $tpl);
                }

                $lines[] = "Dialogue: 0,{$start},{$end},Default,,0,0,0,,{$this->tagBlock($lineTags.$entrance.$colorOverride)}{$body}";
            }
        } else {
            // Karaoke Phrase Style: group words into phrases of 3 words
            $chunks = array_chunk($words, 3);
            foreach ($chunks as $chunk) {
                foreach ($chunk as $index => $activeWord) {
                    $wordStart = (int) $activeWord['start_ms'];
                    $wordEnd = (int) $activeWord['end_ms'];

                    // The entrance plays once, when the phrase first appears.
                    $first = $index === 0;
                    $cursor = 0;
                    $step = max(15, min(45, intdiv((int) (max(1, $wordEnd - $wordStart) * 0.7), 12)));

                    $lineWords = [];
                    foreach ($chunk as $i => $w) {
                        $cleanWord = $this->sanitize($w['word']);
                        if ($cleanWord === '') {
                            continue;
                        }

                        if ($isUppercase) {
                            $cleanWord = mb_strtoupper($cleanWord);
                        }

                        $shown = $first && $anim === 'type'
                            ? $this->typed($cleanWord, $cursor, $step, $tpl)
                            : $cleanWord;

                        if ($i === $index) {
                            $highlight = $tpl['HighlightColour'] ?? '&H0000FFFF';
                            $primary = $tpl['PrimaryColour'] ?? '&H00FFFFFF';
                            // Format: {\c<Highlight>}word{\c<Primary>}
                            $lineWords[] = "{\\c{$highlight}}" . $shown . "{\\c{$primary}}";
                        } else {
                            $lineWords[] = $shown;
                        }
                        $cursor += $step;
                    }

                    $text = implode(' ', $lineWords);
                    if (trim($text) === '') {
                        continue;
                    }

                    $startStr = $this->toAssTime($wordStart);
                    $endStr = $this->toAssTime($wordEnd);
                    $prefix = $this->tagBlock($lineTags.($first && $anim !== 'type' ? $entrance : ''));
                    $lines[] = "Dialogue: 0,{$startStr},{$endStr},Default,,0,0,0,,{$prefix}{$text}";
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
