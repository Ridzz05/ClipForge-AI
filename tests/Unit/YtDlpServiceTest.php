<?php

namespace Tests\Unit;

use App\Exceptions\IngestValidationException;
use App\Services\YtDlpService;
use Tests\TestCase;

class YtDlpServiceTest extends TestCase
{
    private function service(): YtDlpService
    {
        return new YtDlpService(
            ytdlpPath: 'yt-dlp',
            maxFilesize: '2G',
            timeout: 60,
            allowedSchemes: ['http', 'https'],
        );
    }

    public function test_accepts_a_public_https_url(): void
    {
        // A well-known public host; assertSafeUrl must not throw.
        $this->service()->assertSafeUrl('https://www.youtube.com/watch?v=dQw4w9WgXcQ');
        $this->assertTrue(true);
    }

    public function test_rejects_non_http_schemes(): void
    {
        // ftp/gopher have hosts and hit the scheme check; file:// has no host
        // and is rejected as invalid first — both must be blocked either way.
        foreach (['ftp://example.com/x', 'gopher://example.com/x', 'file:///etc/passwd'] as $url) {
            try {
                $this->service()->assertSafeUrl($url);
                $this->fail("Expected rejection for {$url}");
            } catch (IngestValidationException $e) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_rejects_malformed_url(): void
    {
        $this->expectException(IngestValidationException::class);
        $this->service()->assertSafeUrl('not a url at all');
    }

    public function test_rejects_loopback_address_ssrf(): void
    {
        $this->expectException(IngestValidationException::class);
        $this->service()->assertSafeUrl('http://127.0.0.1/video.mp4');
    }

    public function test_rejects_private_network_addresses_ssrf(): void
    {
        foreach (['http://10.0.0.5/x', 'http://192.168.1.1/x', 'http://172.16.0.1/x'] as $url) {
            try {
                $this->service()->assertSafeUrl($url);
                $this->fail("Expected SSRF rejection for {$url}");
            } catch (IngestValidationException $e) {
                $this->assertStringContainsStringIgnoringCase('private', $e->getMessage());
            }
        }
    }

    public function test_rejects_ipv6_loopback(): void
    {
        $this->expectException(IngestValidationException::class);
        $this->service()->assertSafeUrl('http://[::1]/video.mp4');
    }

    public function test_rejects_link_local_metadata_address(): void
    {
        // 169.254.169.254 is the cloud metadata endpoint — classic SSRF target.
        $this->expectException(IngestValidationException::class);
        $this->service()->assertSafeUrl('http://169.254.169.254/latest/meta-data/');
    }
}
