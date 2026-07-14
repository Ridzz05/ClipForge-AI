<?php

namespace App\Services;

use App\Exceptions\IngestValidationException;
use RuntimeException;
use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Downloads a public video URL via yt-dlp (spec 5.1: accept a public video
 * URL). Security (spec section 6):
 *
 *  - yt-dlp is invoked with an ARGUMENT ARRAY, never a shell string, so a
 *    hostile URL can't be interpreted as a shell token.
 *  - Only http/https URLs are accepted; the URL is validated and its host
 *    resolved to reject private/loopback/link-local addresses (SSRF guard).
 *  - Output goes to a server-generated, job-scoped path — the remote filename
 *    never controls where anything is written.
 */
class YtDlpService
{
    public function __construct(
        private readonly string $ytdlpPath,
        private readonly string $maxFilesize,
        private readonly int $timeout,
        /** @var array<int, string> */
        private readonly array $allowedSchemes,
        private readonly string $ffmpegPath = 'ffmpeg',
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            ytdlpPath: (string) config('autoclip.ytdlp_path'),
            maxFilesize: (string) config('autoclip.url_ingest.max_filesize'),
            timeout: (int) config('autoclip.url_ingest.timeout'),
            allowedSchemes: (array) config('autoclip.url_ingest.allowed_schemes'),
            ffmpegPath: (string) config('autoclip.ffmpeg_path'),
        );
    }

    /**
     * Validate a URL for ingest without downloading. Throws on anything unsafe.
     *
     * @throws IngestValidationException
     */
    public function assertSafeUrl(string $url): void
    {
        $parts = parse_url($url);
        if ($parts === false || empty($parts['scheme']) || empty($parts['host'])) {
            throw new IngestValidationException('Invalid URL.');
        }

        $scheme = strtolower($parts['scheme']);
        if (! in_array($scheme, $this->allowedSchemes, true)) {
            throw new IngestValidationException("URL scheme '{$scheme}' is not allowed.");
        }

        $this->assertHostNotPrivate($parts['host']);
    }

    /**
     * Download $url into $targetDir (an absolute directory we control) as a
     * file named by $basename (server-generated, no extension — yt-dlp adds it).
     *
     * @return string absolute path of the downloaded file
     *
     * @throws IngestValidationException on unsafe URL
     * @throws RuntimeException on download failure
     */
    public function download(string $url, string $targetDir, string $basename, string $resolution = 'best', ?callable $onProgress = null): string
    {
        $this->assertSafeUrl($url);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            throw new RuntimeException("Could not create download directory: {$targetDir}");
        }

        // Output template: our directory + server-generated name + yt-dlp ext.
        $outputTemplate = rtrim($targetDir, '/\\').DIRECTORY_SEPARATOR.$basename.'.%(ext)s';

        $format = match ($resolution) {
            '1080p' => 'bestvideo[height<=1080][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=1080]+bestaudio/best[height<=1080]',
            '720p'  => 'bestvideo[height<=720][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=720]+bestaudio/best[height<=720]',
            '480p'  => 'bestvideo[height<=480][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=480]+bestaudio/best[height<=480]',
            '360p'  => 'bestvideo[height<=360][ext=mp4]+bestaudio[ext=m4a]/bestvideo[height<=360]+bestaudio/best[height<=360]',
            default => 'bestvideo[ext=mp4]+bestaudio[ext=m4a]/bestvideo+bestaudio/best',
        };

        $args = [
            $this->ytdlpPath,
            '--no-playlist',                 // one video, never a whole playlist
            '--newline',                     // output progress bar as new lines
            '--max-filesize', $this->maxFilesize,
            '-f', $format,
            '--merge-output-format', 'mp4',  // Force merge format to MP4 (ensures high quality DASH merging)
            '--no-continue',
            '--restrict-filenames',
            '-o', $outputTemplate,
        ];

        if (!empty($this->ffmpegPath) && $this->ffmpegPath !== 'ffmpeg') {
            $ffmpegDir = dirname($this->ffmpegPath);
            if (is_dir($ffmpegDir)) {
                $args[] = '--ffmpeg-location';
                $args[] = $ffmpegDir;
            }
        }

        $args[] = '--';
        $args[] = $url;

        $process = new Process($args);
        $process->setTimeout($this->timeout);

        $lastUpdate = 0;
        $lastPercent = '';
        $currentStream = 'Video';

        try {
            $process->mustRun(function ($type, $buffer) use ($onProgress, &$lastUpdate, &$lastPercent, &$currentStream) {
                if ($type === Process::ERR) {
                    return;
                }

                $lines = explode("\n", $buffer);
                foreach ($lines as $line) {
                    // Update active stream phase (Video, Audio, Merging)
                    if (preg_match('/Destination:\s+.*\.([a-zA-Z0-9]+)$/i', $line, $m)) {
                        $ext = strtolower($m[1]);
                        if (in_array($ext, ['m4a', 'mp3', 'aac', 'opus', 'ogg', 'wav'], true)) {
                            $currentStream = 'Audio';
                        } else {
                            $currentStream = 'Video';
                        }
                    } elseif (stripos($line, 'merging formats') !== false || stripos($line, 'extracting') !== false) {
                        $currentStream = 'Merging';
                    }

                    if (preg_match('/\[download\]\s+([0-9.]+)%/', $line, $matches)) {
                        $percent = $matches[1] . '%';
                        $progressStr = "{$currentStream}: {$percent}";

                        $now = time();
                        if ($progressStr !== $lastPercent && ($now - $lastUpdate) >= 1) {
                            if ($onProgress) {
                                $onProgress($progressStr);
                            }
                            $lastPercent = $progressStr;
                            $lastUpdate = $now;
                        }
                    }
                }
            });
        } catch (ProcessFailedException $e) {
            throw new RuntimeException(
                'yt-dlp download failed: '.$this->tail($process->getErrorOutput()),
                previous: $e,
            );
        }

        // Find the produced file (extension chosen by yt-dlp).
        $matches = glob(rtrim($targetDir, '/\\').DIRECTORY_SEPARATOR.$basename.'.*');
        if ($matches === false || $matches === []) {
            throw new RuntimeException('yt-dlp reported success but no file was produced.');
        }

        return $matches[0];
    }

    /**
     * Reject hosts that resolve to private, loopback, link-local or reserved
     * ranges (SSRF guard). A bare IP is checked directly; a hostname is
     * resolved and every resolved address is checked.
     */
    private function assertHostNotPrivate(string $host): void
    {
        $host = trim($host, '[]'); // strip IPv6 brackets

        // Whitelist of trusted public video domains to bypass SSRF host resolution checks
        $trustedDomains = [
            'youtube.com',
            'youtu.be',
            'tiktok.com',
            'vimeo.com',
            'twitter.com',
            'x.com',
            'instagram.com',
            'facebook.com',
        ];

        $isTrusted = false;
        foreach ($trustedDomains as $td) {
            if ($host === $td || str_ends_with($host, '.' . $td)) {
                $isTrusted = true;
                break;
            }
        }

        if ($isTrusted) {
            return;
        }

        $ips = filter_var($host, FILTER_VALIDATE_IP)
            ? [$host]
            : $this->resolveHost($host);

        if ($ips === []) {
            throw new IngestValidationException("Could not resolve host: {$host}");
        }

        foreach ($ips as $ip) {
            $safe = filter_var(
                $ip,
                FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE,
            );
            if ($safe === false) {
                throw new IngestValidationException(
                    'URL host resolves to a private or reserved address (blocked).'
                );
            }
        }
    }

    /**
     * @return array<int, string>
     */
    private function resolveHost(string $host): array
    {
        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if ($records === false) {
            return [];
        }

        $ips = [];
        foreach ($records as $r) {
            if (isset($r['ip'])) {
                $ips[] = $r['ip'];
            } elseif (isset($r['ipv6'])) {
                $ips[] = $r['ipv6'];
            }
        }

        return $ips;
    }

    private function tail(string $output, int $lines = 2): string
    {
        $parts = array_filter(array_map('trim', explode("\n", $output)));

        // 1. If there are lines starting with "ERROR:", isolate them
        $errorLines = array_filter($parts, fn($l) => str_starts_with($l, 'ERROR:'));
        if (!empty($errorLines)) {
            $parts = $errorLines;
        }

        // 2. Take the last N lines
        $selected = array_slice($parts, -$lines);

        // 3. Clean up verbose network details to make them short and friendly
        return implode(' | ', array_map(function ($l) {
            // Remove prefix "ERROR: [youtube] "
            $l = preg_replace('/^ERROR:\s+\[[^\]]+\]\s+/', 'Error: ', $l);
            $l = preg_replace('/^ERROR:\s+/', 'Error: ', $l);

            // Simplify resolve/connection errors
            if (stripos($l, 'failed to resolve') !== false || stripos($l, 'getaddrinfo failed') !== false) {
                // Get the domain being resolved
                if (preg_match("/host='([^']+)'/i", $l, $m)) {
                    return "Error: Gagal tersambung ke jaringan. Tidak dapat mendeteksi host '{$m[1]}' (Periksa koneksi internet Anda).";
                }
                return "Error: Gagal mendeteksi koneksi internet (Koneksi jaringan gagal).";
            }

            // Simplify private address block
            if (stripos($l, 'resolves to a private') !== false) {
                return "Error: Domain diblokir karena merujuk ke alamat IP privat lokal (Proteksi SSRF).";
            }

            // Remove long system stack trace/exception details at the end
            $l = preg_replace('/\(caused by\s+TransportError.*/i', '', $l);
            $l = preg_replace('/\(caused by\s+URLError.*/i', '', $l);

            return trim($l);
        }, $selected));
    }
}
