<?php

namespace Tests\Unit;

use App\Services\Scoring\OllamaService;
use Illuminate\Http\Client\Factory as HttpFactory;
use RuntimeException;
use Tests\TestCase;

class OllamaServiceTest extends TestCase
{
    private function service(HttpFactory $http): OllamaService
    {
        config(['autoclip.llm.driver' => 'ollama']);
        return new OllamaService($http, 'http://ollama.test', 'qwen2.5:7b', 60);
    }

    private array $segments = [
        ['start_ms' => 0, 'end_ms' => 2000, 'text' => 'hello world'],
    ];

    public function test_parses_json_from_ollama_response_envelope(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response([
                'response' => json_encode(['highlights' => [
                    ['start_ms' => 0, 'end_ms' => 30000, 'hook_score' => 80, 'rationale' => 'good'],
                ]]),
            ]),
        ]);

        $decoded = $this->service($http)->scoreBatch($this->segments);

        $this->assertArrayHasKey('highlights', $decoded);
        $this->assertSame(80, $decoded['highlights'][0]['hook_score']);
    }

    public function test_sends_format_json_and_the_model_name(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['response' => '{"highlights":[]}'])]);

        $this->service($http)->scoreBatch($this->segments);

        $http->assertSent(function ($request) {
            $body = $request->data();

            return $request->url() === 'http://ollama.test/api/generate'
                && $body['format'] === 'json'
                && $body['model'] === 'qwen2.5:7b'
                && $body['stream'] === false
                && str_contains($body['prompt'], 'hello world');
        });
    }

    public function test_throws_on_http_error(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response('nope', 500)]);

        $this->expectException(RuntimeException::class);
        $this->service($http)->scoreBatch($this->segments);
    }

    public function test_throws_when_response_string_is_not_json(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['response' => 'this is not json'])]);

        $this->expectException(RuntimeException::class);
        $this->service($http)->scoreBatch($this->segments);
    }

    public function test_throws_when_envelope_missing_response(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['done' => true])]);

        $this->expectException(RuntimeException::class);
        $this->service($http)->scoreBatch($this->segments);
    }
}
