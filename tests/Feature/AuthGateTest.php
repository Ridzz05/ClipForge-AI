<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthGateTest extends TestCase
{
    use RefreshDatabase;

    public function test_gate_rejects_missing_or_wrong_credentials_and_accepts_correct_ones(): void
    {
        config(['autoclip.access.user' => 'op', 'autoclip.access.password' => 's3cret']);

        $this->get('/')
            ->assertStatus(401)
            ->assertHeader('WWW-Authenticate', 'Basic realm="ClipForge"');

        $this->get('/', ['Authorization' => 'Basic '.base64_encode('op:wrong')])
            ->assertStatus(401);

        $this->get('/', ['Authorization' => 'Basic '.base64_encode('op:s3cret')])
            ->assertStatus(200);
    }

    public function test_gate_also_protects_api_routes(): void
    {
        config(['autoclip.access.user' => 'op', 'autoclip.access.password' => 's3cret']);

        $this->postJson('/api/candidates/1/approve')->assertStatus(401);
    }

    public function test_gate_is_disabled_when_credentials_are_unset(): void
    {
        config(['autoclip.access.user' => null, 'autoclip.access.password' => null]);

        $this->get('/')->assertStatus(200);
    }
}
