<?php

namespace Tests\Unit;

use App\Services\Reframe\CaptionRenderer;
use Tests\TestCase;

class CaptionRendererTest extends TestCase
{
    private CaptionRenderer $renderer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->renderer = new CaptionRenderer;
    }

    private array $words = [
        ['word' => 'hello', 'start_ms' => 0, 'end_ms' => 500],
        ['word' => 'world', 'start_ms' => 500, 'end_ms' => 1250],
    ];

    public function test_produces_valid_ass_header_and_dialogue_lines(): void
    {
        $ass = $this->renderer->renderAss($this->words, 'default', 1080, 1920);

        $this->assertStringContainsString('[Script Info]', $ass);
        $this->assertStringContainsString('PlayResX: 1080', $ass);
        $this->assertStringContainsString('PlayResY: 1920', $ass);
        $this->assertStringContainsString('[V4+ Styles]', $ass);
        $this->assertStringContainsString('[Events]', $ass);
        $this->assertStringContainsString('Dialogue: 0,', $ass);
        $this->assertStringContainsString('hello', $ass);
        $this->assertStringContainsString('world', $ass);
    }

    public function test_formats_ass_timestamps_as_centiseconds(): void
    {
        $words = [['word' => 'x', 'start_ms' => 61_230, 'end_ms' => 62_000]];
        $ass = $this->renderer->renderAss($words, 'default', 1080, 1920);
        // 61230ms -> 0:01:01.23
        $this->assertStringContainsString('0:01:01.23', $ass);
    }

    public function test_unknown_style_falls_back_to_default(): void
    {
        $ass = $this->renderer->renderAss($this->words, 'does-not-exist', 1080, 1920);
        $this->assertStringContainsString('Style: Default,Arial', $ass);
    }

    public function test_known_style_template_is_applied(): void
    {
        $ass = $this->renderer->renderAss($this->words, 'karaoke_yellow', 1080, 1920);
        // Yellow primary colour from the template.
        $this->assertStringContainsString('&H0000FFFF', $ass);
    }

    public function test_caption_text_cannot_inject_ass_override_tags(): void
    {
        // Transcript is untrusted; a word containing ASS override syntax must be
        // neutralised so it can't restyle/break the burn-in.
        $words = [[
            'word' => '{\\pos(0,0)}evil\nnewline',
            'start_ms' => 0, 'end_ms' => 500,
        ]];
        $ass = $this->renderer->renderAss($words, 'default', 1080, 1920);

        $this->assertStringNotContainsString('{\\pos', $ass);
        $this->assertStringNotContainsString('{', $this->dialogueBlock($ass));
        $this->assertStringNotContainsString('}', $this->dialogueBlock($ass));
        // Newline was flattened, not left to break the Dialogue line.
        $dialogueLines = array_filter(
            explode("\n", $ass),
            fn ($l) => str_starts_with($l, 'Dialogue:'),
        );
        $this->assertCount(1, $dialogueLines);
    }

    public function test_empty_words_are_skipped(): void
    {
        $words = [
            ['word' => '', 'start_ms' => 0, 'end_ms' => 100],
            ['word' => '  ', 'start_ms' => 100, 'end_ms' => 200],
        ];
        $ass = $this->renderer->renderAss($words, 'default', 1080, 1920);
        $this->assertStringNotContainsString('Dialogue:', $ass);
    }

    // --- Campaign CTA overlay --------------------------------------------

    public function test_cta_overlay_is_burned_spanning_the_clip(): void
    {
        $ass = $this->renderer->renderAss(
            $this->words, 'default', 1080, 1920,
            ctaText: "IT'S OUT. IT'S ACTUALLY OUT.",
            clipDurationMs: 30_000,
        );

        // A dedicated CTA style and a Dialogue line using it, spanning 0..30s.
        $this->assertStringContainsString('Style: CTA,', $ass);
        $this->assertStringContainsString(",CTA,,0,0,0,,IT'S OUT. IT'S ACTUALLY OUT.", $ass);
        $this->assertStringContainsString('0:00:30.00', $ass); // clip end
    }

    public function test_no_cta_line_when_text_empty(): void
    {
        $ass = $this->renderer->renderAss($this->words, 'default', 1080, 1920, ctaText: '', clipDurationMs: 30_000);
        $this->assertStringNotContainsString(',CTA,,', $ass);
    }

    public function test_cta_text_cannot_inject_ass_override_tags(): void
    {
        // CTA text is operator input; still sanitised like captions.
        $ass = $this->renderer->renderAss(
            $this->words, 'default', 1080, 1920,
            ctaText: "{\\pos(0,0)}hack\nme", clipDurationMs: 10_000,
        );

        $events = $this->dialogueBlock($ass);
        $this->assertStringNotContainsString('{', $events);
        $this->assertStringNotContainsString('}', $events);
        // The CTA collapsed to a single line (newline flattened).
        $ctaLines = array_filter(
            explode("\n", $ass),
            fn ($l) => str_contains($l, ',CTA,,'),
        );
        $this->assertCount(1, $ctaLines);
    }

    private function dialogueBlock(string $ass): string
    {
        $pos = strpos($ass, '[Events]');

        return $pos === false ? '' : substr($ass, $pos);
    }
}
