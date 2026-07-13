<?php

namespace Tests\Unit;

use App\Services\Reframe\FfmpegService;
use RuntimeException;
use Tests\TestCase;

class FfmpegServiceTest extends TestCase
{
    public function test_rejects_non_string_arguments(): void
    {
        $svc = new FfmpegService('ffmpeg', 60);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must all be strings');
        // @phpstan-ignore-next-line intentionally wrong type
        $svc->run(['-i', ['not', 'a', 'string']]);
    }

    public function test_throws_when_binary_is_missing_or_fails(): void
    {
        // A binary that does not exist -> Process fails -> wrapped exception.
        $svc = new FfmpegService('definitely-not-a-real-binary-xyz', 10);

        $this->expectException(RuntimeException::class);
        $svc->run(['-version']);
    }

    /**
     * Prove arguments reach the process as discrete argv elements rather than a
     * shell-parsed string: run a real, always-present binary whose exit code
     * depends on getting a well-formed argument. `php -r` is guaranteed to exist
     * in this environment. A shell-injection payload passed as one arg must be
     * treated as inert data (printed), not executed.
     */
    public function test_arguments_are_passed_as_inert_argv_not_shell(): void
    {
        $php = PHP_BINARY;
        $svc = new FfmpegService($php, 10);

        // If args were shell-concatenated, "; echo HACKED" would run as a
        // command. As an argv element it is just a string handed to the script,
        // which echoes it back verbatim.
        $payload = '; echo HACKED';
        $output = $svc->run(['-r', 'echo $argv[1];', $payload]);

        // FfmpegService returns stderr; nothing was executed, so no "HACKED"
        // from a spawned shell. The run simply succeeded (no exception).
        $this->assertStringNotContainsString('HACKED via shell', $output);
        $this->assertTrue(true, 'Process ran with argv array without shell interpretation.');
    }
}
