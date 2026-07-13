<?php

namespace App\Services\Reframe;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * The single choke point for invoking ffmpeg (spec section 6: command
 * injection). Every call goes through run() with an ARGUMENT ARRAY — arguments
 * are passed to the OS exec directly, never concatenated into a shell string,
 * so a hostile filename or caption can never be interpreted as a shell token.
 *
 * Callers build filter graphs; this class only executes and (optionally) marks
 * output. It never accepts a raw command string.
 */
class FfmpegService
{
    public function __construct(
        private readonly string $ffmpegPath,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            ffmpegPath: (string) config('autoclip.ffmpeg_path'),
            timeout: (int) config('autoclip.timeouts.reframe'),
        );
    }

    /**
     * Run ffmpeg with the given argument list (WITHOUT the leading binary; it
     * is prepended here). Returns stderr (ffmpeg logs progress there) on
     * success; throws on non-zero exit.
     *
     * @param  array<int, string>  $args
     */
    public function run(array $args): string
    {
        // Guard: everything must be a scalar string. No arrays / nulls that a
        // caller might accidentally interpolate.
        foreach ($args as $arg) {
            if (! is_string($arg)) {
                throw new RuntimeException('ffmpeg arguments must all be strings.');
            }
        }

        $process = new Process([$this->ffmpegPath, ...$args]);
        $process->setTimeout($this->timeout);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'ffmpeg failed: '.$this->tail($process->getErrorOutput()),
                previous: $e,
            );
        }

        return $process->getErrorOutput();
    }

    /** Keep only the last few lines of ffmpeg stderr for error messages. */
    private function tail(string $output, int $lines = 5): string
    {
        $parts = array_filter(array_map('trim', explode("\n", $output)));

        return implode(' | ', array_slice($parts, -$lines));
    }
}
