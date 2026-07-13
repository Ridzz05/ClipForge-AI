<?php

namespace Tests\Unit;

use App\Services\Reframe\ReframePlanner;
use Tests\TestCase;

class ReframePlannerTest extends TestCase
{
    private ReframePlanner $planner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->planner = new ReframePlanner;
    }

    public function test_crop_size_for_landscape_1080p_is_9_by_16(): void
    {
        $crop = $this->planner->cropSize(1920, 1080);
        // Height-driven: 1080 * 9/16 = 607.5 -> 608 wide, full height.
        $this->assertSame(608, $crop['width']);
        $this->assertSame(1080, $crop['height']);
        // Aspect is ~9:16.
        $this->assertEqualsWithDelta(9 / 16, $crop['width'] / $crop['height'], 0.01);
    }

    public function test_crop_size_for_already_portrait_source_is_width_driven(): void
    {
        $crop = $this->planner->cropSize(1080, 1920);
        $this->assertSame(1080, $crop['width']);
        $this->assertLessThanOrEqual(1920, $crop['height']);
        $this->assertTrue($this->planner->isAlreadyVertical(1080, 1920));
    }

    public function test_no_faces_yields_static_centre_crop(): void
    {
        $path = $this->planner->panPath(1920, 1080, []);
        $this->assertCount(1, $path);
        // Centre: (1920 - 608) / 2 = 656.
        $this->assertSame(656, $path[0]['x']);
    }

    public function test_pan_path_keeps_window_inside_frame(): void
    {
        $centers = [
            ['t_ms' => 0, 'cx' => 0.0],   // far left
            ['t_ms' => 1000, 'cx' => 1.0], // far right
        ];
        $path = $this->planner->panPath(1920, 1080, $centers);

        $maxX = 1920 - 608;
        foreach ($path as $p) {
            $this->assertGreaterThanOrEqual(0, $p['x']);
            $this->assertLessThanOrEqual($maxX, $p['x']);
        }
    }

    public function test_pan_path_is_smoothed_not_jumpy(): void
    {
        // A sudden jump from centre to far right should not fully move in one step.
        $centers = [
            ['t_ms' => 0, 'cx' => 0.5],
            ['t_ms' => 100, 'cx' => 1.0],
        ];
        $path = $this->planner->panPath(1920, 1080, $centers);

        $maxX = 1920 - 608;
        // Second sample must be between the first and the target (smoothing).
        $this->assertGreaterThan($path[0]['x'], $path[1]['x']);
        $this->assertLessThan($maxX, $path[1]['x']);
    }

    public function test_centers_are_sorted_by_time_before_smoothing(): void
    {
        $centers = [
            ['t_ms' => 2000, 'cx' => 0.8],
            ['t_ms' => 0, 'cx' => 0.2],
        ];
        $path = $this->planner->panPath(1920, 1080, $centers);
        $this->assertSame(0, $path[0]['t_ms']);
        $this->assertSame(2000, $path[1]['t_ms']);
    }
}
