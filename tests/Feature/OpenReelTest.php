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
}
