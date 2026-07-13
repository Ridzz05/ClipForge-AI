<?php

namespace App\Services;

use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Thin wrapper around `ffprobe`, always invoked with an argument array so a
 * hostile filename can never be interpreted as a shell token (spec section 6:
 * command injection). Callers pass real filesystem paths only.
 */
class FfprobeService
{
    public function __construct(
        private readonly string $ffprobePath,
    ) {}

    public static function fromConfig(): self
    {
        return new self((string) config('autoclip.ffprobe_path'));
    }

    /**
     * Probe a media file. Returns the decoded ffprobe JSON, or throws if the
     * file is not decodable media (corrupt / not a video — rejected early).
     *
     * @return array<string, mixed>
     */
    public function probe(string $absolutePath): array
    {
        $process = new Process([
            $this->ffprobePath,
            '-v', 'error',
            '-print_format', 'json',
            '-show_format',
            '-show_streams',
            $absolutePath,
        ]);
        $process->setTimeout(60);

        try {
            $process->mustRun();
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'ffprobe rejected the file (corrupt or not media): '.$e->getMessage(),
                previous: $e,
            );
        }

        $decoded = json_decode($process->getOutput(), true);
        if (! is_array($decoded)) {
            throw new RuntimeException('ffprobe returned unparsable output.');
        }

        return $decoded;
    }

    /**
     * Duration in whole seconds, or null if ffprobe reports no duration
     * (e.g. a still image or malformed stream — caller should reject).
     */
    public function durationSeconds(string $absolutePath): ?int
    {
        $data = $this->probe($absolutePath);

        $duration = $data['format']['duration'] ?? null;
        if ($duration === null || ! is_numeric($duration)) {
            return null;
        }

        // Reject files with no video stream: audio-only isn't a clippable video.
        $hasVideoStream = collect($data['streams'] ?? [])
            ->contains(fn ($s) => ($s['codec_type'] ?? null) === 'video');
        if (! $hasVideoStream) {
            throw new RuntimeException('File contains no video stream.');
        }

        return (int) floor((float) $duration);
    }
}
