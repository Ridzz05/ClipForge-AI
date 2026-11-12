<?php

namespace Tests\Feature;

use Tests\TestCase;

class OpenReelTest extends TestCase
{
    public function test_openreel_index_returns_200_with_coop_coep_headers(): void
    {
        $response = $this->get('/openreel');

        $response->assertStatus(200);
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    public function test_openreel_assets_return_correct_mime_and_headers(): void
    {
        $response = $this->get('/openreel/assets/index-BJkXg0Nz.js');

        $response->assertStatus(200);
        $response->assertHeader('Content-Type', 'application/javascript');
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    public function test_openreel_spa_subpath_fallback_returns_index(): void
    {
        $response = $this->get('/openreel/projects/123');

        $response->assertStatus(200);
        $response->assertHeader('Cross-Origin-Opener-Policy', 'same-origin');
        $response->assertHeader('Cross-Origin-Embedder-Policy', 'require-corp');
    }

    public function test_openreel_path_traversal_never_serves_files_outside_the_root(): void
    {
        $envFile = (string) file_get_contents(base_path('.env.example'));

        foreach (['/openreel/..%2f..%2f.env.example', '/openreel/%2e%2e/%2e%2e/.env.example', '/openreel/../../.env.example'] as $uri) {
            $response = $this->get($uri);

            // Either 404 (no assets) or the SPA index fallback; never the target file.
            $this->assertContains($response->getStatusCode(), [200, 404], $uri);
            if ($response->getStatusCode() === 200) {
                $this->assertStringNotContainsString('AUTOCLIP_', $response->streamedContent() ?: (string) $response->getContent(), $uri);
            }
        }

        $this->assertStringContainsString('AUTOCLIP_', $envFile);
    }
}
