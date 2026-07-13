<?php

namespace Tests\Unit;

use App\Services\Reframe\WatermarkCommandBuilder;
use Tests\TestCase;

class WatermarkCommandBuilderTest extends TestCase
{
    private WatermarkCommandBuilder $builder;

    protected function setUp(): void
    {
        parent::setUp();
        $this->builder = new WatermarkCommandBuilder;
    }

    public function test_builds_two_input_overlay_command(): void
    {
        $args = $this->builder->build('/in.mp4', '/wm.png', '/out.mp4');

        // Two -i inputs: clip then watermark.
        $iPositions = array_keys($args, '-i', true);
        $this->assertCount(2, $iPositions);
        $this->assertSame('/in.mp4', $args[$iPositions[0] + 1]);
        $this->assertSame('/wm.png', $args[$iPositions[1] + 1]);

        $filter = $args[array_search('-filter_complex', $args, true) + 1];
        $this->assertStringContainsString('overlay=W-w-', $filter);

        // Output is the final element.
        $this->assertSame('/out.mp4', $args[array_key_last($args)]);
    }

    public function test_all_arguments_are_strings(): void
    {
        foreach ($this->builder->build('/in.mp4', '/wm.png', '/out.mp4') as $arg) {
            $this->assertIsString($arg);
        }
    }

    public function test_margin_is_reflected_in_overlay_expression(): void
    {
        $args = $this->builder->build('/in.mp4', '/wm.png', '/out.mp4', margin: 80);
        $filter = $args[array_search('-filter_complex', $args, true) + 1];
        $this->assertStringContainsString('overlay=W-w-80:H-h-80', $filter);
    }

    /**
     * DoD: ffmpeg argument arrays verified with a malicious filename. Hostile
     * paths remain single inert argv elements.
     */
    public function test_malicious_paths_stay_single_inert_arguments(): void
    {
        $evilIn = '/x/"; rm -rf / #.mp4';
        $evilOut = '/x/$(reboot).mp4';

        $args = $this->builder->build($evilIn, '/wm.png', $evilOut);

        $this->assertContains($evilIn, $args);
        $this->assertContains($evilOut, $args);
        $this->assertSame($evilOut, $args[array_key_last($args)]);

        $iPositions = array_keys($args, '-i', true);
        $this->assertSame($evilIn, $args[$iPositions[0] + 1]);
    }
}
