<?php

namespace Tests\Unit;

use App\Services\Reframe\ReframeCommandBuilder;
use Tests\TestCase;

class ReframeCommandBuilderTest extends TestCase
{
    private ReframeCommandBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new ReframeCommandBuilder;
    }

    private function build(string $input = '/store/videos/a/source.mp4', string $output = '/store/exports/1/out.mp4'): array
    {
        return $this->builder->build(
            inputPath: $input,
            assPath: '/store/exports/1/captions.ass',
            outputPath: $output,
            clip: ['start_ms' => 5000, 'end_ms' => 35000],
            crop: ['width' => 608, 'height' => 1080],
            panX: 656,
            renderW: 1080,
            renderH: 1920,
        );
    }

    public function test_every_argument_is_a_string(): void
    {
        foreach ($this->build() as $arg) {
            $this->assertIsString($arg);
        }
    }

    public function test_seek_and_duration_are_computed_in_seconds(): void
    {
        $args = $this->build();
        $ss = $args[array_search('-ss', $args, true) + 1];
        $t = $args[array_search('-t', $args, true) + 1];

        $this->assertSame('5.000', $ss);   // 5000ms
        $this->assertSame('30.000', $t);   // 30000ms clip
    }

    public function test_filter_graph_contains_crop_scale_and_subtitles(): void
    {
        $args = $this->build();
        $filter = $args[array_search('-vf', $args, true) + 1];

        $this->assertStringContainsString('crop=608:1080:656:0', $filter);
        $this->assertStringContainsString('scale=1080:1920', $filter);
        $this->assertStringContainsString('subtitles=', $filter);
    }

    /**
     * Spec DoD: ffmpeg calls use argument arrays, verified with a malicious
     * filename. Paths are server-generated, but even a hostile-looking path
     * must remain a single inert argument — never split into extra args or a
     * shell fragment.
     */
    public function test_malicious_looking_paths_stay_single_inert_arguments(): void
    {
        $evilInput = '/store/videos/"; rm -rf / #/source.mp4';
        $evilOutput = '/store/exports/$(whoami)/out.mp4';

        $args = $this->build($evilInput, $evilOutput);

        // The exact strings appear as their own array elements — not expanded,
        // not concatenated into another arg.
        $this->assertContains($evilInput, $args);
        $this->assertContains($evilOutput, $args);

        // No argument is a shell operator (would only matter if we shelled out,
        // which we never do — this asserts we didn't build a combined string).
        foreach ($args as $arg) {
            $this->assertStringNotContainsString(' -i ', $arg);
        }

        // Input path is immediately after -i as one element.
        $iPos = array_search('-i', $args, true);
        $this->assertSame($evilInput, $args[$iPos + 1]);
        // Output path is the final element.
        $this->assertSame($evilOutput, $args[array_key_last($args)]);
    }
}
