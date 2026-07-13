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
    ) {}

    public static function fromConfig(): self
    {
        return new self(
            ytdlpPath: (string) config('autoclip.ytdlp_path'),
            maxFilesize: (string) config('autoclip.url_ingest.max_filesize'),
            timeout: (int) config('autoclip.url_ingest.timeout'),
            allowedSchemes: (array) config('autoclip.url_ingest.allowed_schemes'),
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
    public function download(string $url, string $targetDir, string $basename): string
    {
        $this->assertSafeUrl($url);

        if (! is_dir($targetDir) && ! mkdir($targetDir, 0755, true) && ! is_dir($targetDir)) {
            throw new RuntimeException("Could not create download directory: {$targetDir}");
        }

        // Output template: our directory + server-generated name + yt-dlp ext.
        $outputTemplate = rtrim($targetDir, '/\\').DIRECTORY_SEPARATOR.$basename.'.%(ext)s';

        $process = new Process([
            $this->ytdlpPath,
            '--no-playlist',                 // one video, never a whole playlist
            '--no-progress',
            '--max-filesize', $this->maxFilesize,
            // Prefer a single mp4 (avoids needing a merge/remux step downstream).
            '-f', 'best[ext=mp4]/mp4/best',
            '--no-continue',
            '--restrict-filenames',
            '-o', $outputTemplate,
            '--', $url,                      // '--' stops option parsing; URL is data
        ]);
        $process->setTimeout($this->timeout);

        try {
            $process->mustRun();
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

    private function tail(string $output, int $lines = 5): string
    {
        $parts = array_filter(array_map('trim', explode("\n", $output)));

        return implode(' | ', array_slice($parts, -$lines));
    }
}
