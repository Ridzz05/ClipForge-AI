<?php

namespace Tests\Unit;

use App\Services\WhisperService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use RuntimeException;
use Tests\TestCase;

class WhisperServiceTest extends TestCase
{
    private function service(HttpFactory $http): WhisperService
    {
        return new WhisperService($http, 'http://whisper.test', 'small', 30);
    }

    private function tempMediaPath(): string
    {
        $path = tempnam(sys_get_temp_dir(), 'whisper_test_');
        file_put_contents($path, 'fake-media');

        return $path;
    }

    public function test_normalizes_seconds_to_milliseconds_and_flattens_words(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response([
                'language' => 'en',
                'text' => 'hello world',
                'segments' => [
                    [
                        'start' => 0.0,
                        'end' => 1.25,
                        'text' => 'hello world',
                        'words' => [
                            ['word' => 'hello', 'start' => 0.0, 'end' => 0.5],
                            ['word' => 'world', 'start' => 0.5, 'end' => 1.25],
                        ],
                    ],
                ],
            ]),
        ]);

        $result = $this->service($http)->transcribe($this->tempMediaPath());

        $this->assertSame('en', $result['language']);
        $this->assertSame('hello world', $result['full_text']);
        $this->assertCount(1, $result['segments']);

        $seg = $result['segments'][0];
        $this->assertSame(0, $seg['start_ms']);
        $this->assertSame(1250, $seg['end_ms']); // 1.25s -> 1250ms
        $this->assertSame(500, $seg['words'][1]['start_ms']);
        $this->assertSame(1250, $seg['words'][1]['end_ms']);
    }

    public function test_derives_full_text_when_service_omits_it(): void
    {
        $http = new HttpFactory;
        $http->fake([
            '*' => $http->response([
                'language' => 'id',
                'segments' => [
                    ['start' => 0, 'end' => 1, 'text' => 'satu', 'words' => []],
                    ['start' => 1, 'end' => 2, 'text' => 'dua', 'words' => []],
                ],
            ]),
        ]);

        $result = $this->service($http)->transcribe($this->tempMediaPath());
        $this->assertSame('satu dua', $result['full_text']);
    }

    public function test_throws_on_http_error(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response('boom', 500)]);

        $this->expectException(RuntimeException::class);
        $this->service($http)->transcribe($this->tempMediaPath());
    }

    public function test_throws_when_segments_missing(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['language' => 'en'])]);

        $this->expectException(RuntimeException::class);
        $this->service($http)->transcribe($this->tempMediaPath());
    }

    public function test_throws_when_media_file_absent(): void
    {
        $http = new HttpFactory;
        $http->fake();

        $this->expectException(RuntimeException::class);
        $this->service($http)->transcribe('/no/such/file.mp4');
    }
}
