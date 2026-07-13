<?php

namespace Tests\Unit;

use App\Services\Scoring\HighlightSchema;
use App\Services\Scoring\InvalidHighlightSchemaException;
use Tests\TestCase;

class HighlightSchemaTest extends TestCase
{
    private HighlightSchema $schema;

    private int $videoMs = 600_000; // 10 min

    protected function setUp(): void
    {
        parent::setUp();
        $this->schema = new HighlightSchema;
    }

    public function test_accepts_a_clean_list_of_highlights(): void
    {
        $raw = [
            ['start_ms' => 1000, 'end_ms' => 31000, 'hook_score' => 82, 'rationale' => 'strong hook'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);

        $this->assertCount(1, $out);
        $this->assertSame(1000, $out[0]['start_ms']);
        $this->assertSame(31000, $out[0]['end_ms']);
        $this->assertSame(82, $out[0]['hook_score']);
        $this->assertSame('strong hook', $out[0]['rationale']);
    }

    public function test_unwraps_a_highlights_object(): void
    {
        $raw = ['highlights' => [
            ['start_ms' => 0, 'end_ms' => 10000, 'hook_score' => 50, 'rationale' => 'ok'],
        ]];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertCount(1, $out);
    }

    public function test_clamps_out_of_range_hook_score(): void
    {
        $raw = [
            ['start_ms' => 0, 'end_ms' => 10000, 'hook_score' => 999, 'rationale' => 'x'],
            ['start_ms' => 0, 'end_ms' => 10000, 'hook_score' => -5, 'rationale' => 'y'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertSame(100, $out[0]['hook_score']);
        $this->assertSame(0, $out[1]['hook_score']);
    }

    public function test_coerces_numeric_strings(): void
    {
        $raw = [
            ['start_ms' => '2000', 'end_ms' => '12000', 'hook_score' => '75.4', 'rationale' => 'z'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertSame(2000, $out[0]['start_ms']);
        $this->assertSame(12000, $out[0]['end_ms']);
        $this->assertSame(75, $out[0]['hook_score']);
    }

    public function test_snaps_end_into_video_bounds(): void
    {
        // Start near the end so the snapped clip stays within the max length.
        $raw = [
            ['start_ms' => $this->videoMs - 30_000, 'end_ms' => $this->videoMs + 50_000, 'hook_score' => 60, 'rationale' => 'a'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertSame($this->videoMs, $out[0]['end_ms']);
    }

    public function test_drops_inverted_and_out_of_bounds_ranges(): void
    {
        $raw = [
            ['start_ms' => 5000, 'end_ms' => 4000, 'hook_score' => 90, 'rationale' => 'inverted'],
            ['start_ms' => $this->videoMs + 1, 'end_ms' => $this->videoMs + 20000, 'hook_score' => 90, 'rationale' => 'past end'],
            ['start_ms' => 0, 'end_ms' => 20000, 'hook_score' => 70, 'rationale' => 'valid'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertCount(1, $out);
        $this->assertSame('valid', $out[0]['rationale']);
    }

    public function test_drops_clips_that_are_too_short_or_too_long(): void
    {
        $raw = [
            ['start_ms' => 0, 'end_ms' => 1000, 'hook_score' => 80, 'rationale' => 'too short'], // 1s
            ['start_ms' => 0, 'end_ms' => 200_000, 'hook_score' => 80, 'rationale' => 'too long'], // 200s
            ['start_ms' => 0, 'end_ms' => 30000, 'hook_score' => 80, 'rationale' => 'just right'],
        ];

        $out = $this->schema->validate($raw, 600_000);
        $this->assertCount(1, $out);
        $this->assertSame('just right', $out[0]['rationale']);
    }

    public function test_ignores_extra_fields_and_only_keeps_schema_fields(): void
    {
        $raw = [[
            'start_ms' => 0, 'end_ms' => 15000, 'hook_score' => 65,
            'rationale' => 'ok',
            'shell_command' => 'rm -rf /',   // must be ignored entirely
            'output_path' => '../../etc/passwd',
        ]];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertSame(
            ['start_ms', 'end_ms', 'hook_score', 'rationale'],
            array_keys($out[0]),
        );
    }

    public function test_rationale_is_length_capped_and_kept_inert(): void
    {
        $evil = str_repeat('A', 5000).'; DROP TABLE videos;';
        $raw = [['start_ms' => 0, 'end_ms' => 15000, 'hook_score' => 50, 'rationale' => $evil]];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertLessThanOrEqual(
            HighlightSchema::MAX_RATIONALE_LEN,
            mb_strlen($out[0]['rationale']),
        );
        // It's stored as a plain string, never executed — a SQL-ish payload is
        // just inert text here.
        $this->assertIsString($out[0]['rationale']);
    }

    public function test_throws_when_output_is_not_an_array(): void
    {
        $this->expectException(InvalidHighlightSchemaException::class);
        $this->schema->validate('totally not json array', $this->videoMs);
    }

    public function test_throws_when_no_valid_items_survive(): void
    {
        $raw = [
            ['start_ms' => 5000, 'end_ms' => 4000, 'hook_score' => 90, 'rationale' => 'inverted'],
            ['nonsense' => true],
        ];

        $this->expectException(InvalidHighlightSchemaException::class);
        $this->schema->validate($raw, $this->videoMs);
    }

    // --- Campaign compliance: minimum clip length ------------------------

    public function test_default_minimum_is_ten_seconds_from_config(): void
    {
        // Campaign rule: clips must be >= 10s. Default config enforces it.
        $this->assertSame(10_000, $this->schema->minClipMs());
    }

    public function test_rejects_clips_shorter_than_ten_seconds(): void
    {
        $raw = [
            ['start_ms' => 0, 'end_ms' => 9_000, 'hook_score' => 95, 'rationale' => '9s — too short'],
            ['start_ms' => 0, 'end_ms' => 9_999, 'hook_score' => 95, 'rationale' => 'just under'],
            ['start_ms' => 0, 'end_ms' => 10_000, 'hook_score' => 60, 'rationale' => 'exactly 10s ok'],
        ];

        $out = $this->schema->validate($raw, $this->videoMs);
        $this->assertCount(1, $out);
        $this->assertSame('exactly 10s ok', $out[0]['rationale']);
    }

    public function test_minimum_is_configurable(): void
    {
        // A campaign requiring >= 30s clips.
        $schema = new HighlightSchema(minClipMs: 30_000);
        $this->assertSame(30_000, $schema->minClipMs());

        $raw = [
            ['start_ms' => 0, 'end_ms' => 20_000, 'hook_score' => 90, 'rationale' => '20s'],
            ['start_ms' => 0, 'end_ms' => 35_000, 'hook_score' => 70, 'rationale' => '35s'],
        ];

        $out = $schema->validate($raw, $this->videoMs);
        $this->assertCount(1, $out);
        $this->assertSame('35s', $out[0]['rationale']);
    }

    public function test_reads_bounds_from_config(): void
    {
        config(['autoclip.clips.min_ms' => 15_000]);
        $schema = new HighlightSchema;
        $this->assertSame(15_000, $schema->minClipMs());
    }
}
