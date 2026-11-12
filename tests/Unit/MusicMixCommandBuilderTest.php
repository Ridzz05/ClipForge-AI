<?php

namespace Tests\Unit;

use App\Services\Reframe\MusicMixCommandBuilder;
use Tests\TestCase;

class MusicMixCommandBuilderTest extends TestCase
{
    public function test_loops_music_and_mixes_it_under_the_clip_audio(): void
    {
        $args = (new MusicMixCommandBuilder)->build('/tmp/clip.mp4', '/tmp/song.mp3', '/tmp/out.mp4', 20);

        $loopIdx = array_search('-stream_loop', $args, true);
        $this->assertNotFalse($loopIdx);
        $this->assertSame('-1', $args[$loopIdx + 1]);
        // The looped music is the second input (after the clip).
        $this->assertSame('-i', $args[$loopIdx + 2]);
        $this->assertSame('/tmp/song.mp3', $args[$loopIdx + 3]);

        $filter = $args[array_search('-filter_complex', $args, true) + 1];
        $this->assertStringContainsString('[1:a]volume=0.20[bg]', $filter);
        $this->assertStringContainsString('amix', $filter);
        $this->assertSame('copy', $args[array_search('-c:v', $args, true) + 1]);
    }

    public function test_volume_is_clamped_to_the_full_scale(): void
    {
        $mix = new MusicMixCommandBuilder;
        $high = $mix->build('/a.mp4', '/b.mp3', '/c.mp4', 150);
        $filter = $high[array_search('-filter_complex', $high, true) + 1];
        $this->assertStringContainsString('volume=1.00', $filter);

        $low = $mix->build('/a.mp4', '/b.mp3', '/c.mp4', -10);
        $lowFilter = $low[array_search('-filter_complex', $low, true) + 1];
        $this->assertStringContainsString('volume=0.00', $lowFilter);
    }
}
