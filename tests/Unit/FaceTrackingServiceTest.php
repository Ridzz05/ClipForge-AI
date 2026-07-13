<?php

namespace Tests\Unit;

use App\Services\Reframe\FaceTrackingService;
use Illuminate\Http\Client\Factory as HttpFactory;
use Tests\TestCase;

class FaceTrackingServiceTest extends TestCase
{
    private function service(HttpFactory $http): FaceTrackingService
    {
        return new FaceTrackingService($http, 'http://face.test', 30);
    }

    private function tempMedia(): string
    {
        $p = tempnam(sys_get_temp_dir(), 'face_test_');
        file_put_contents($p, 'fake-media');

        return $p;
    }

    public function test_normalizes_centers_and_clamps_cx(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response([
            'centers' => [
                ['t_ms' => 0, 'cx' => 0.4],
                ['t_ms' => 250, 'cx' => 1.5],   // clamps to 1.0
                ['t_ms' => 500, 'cx' => -0.2],  // clamps to 0.0
            ],
        ])]);

        $out = $this->service($http)->sampleCenters($this->tempMedia(), 0, 1000);

        $this->assertCount(3, $out);
        $this->assertSame(0.4, $out[0]['cx']);
        $this->assertSame(1.0, $out[1]['cx']);
        $this->assertSame(0.0, $out[2]['cx']);
    }

    public function test_drops_malformed_center_entries(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response([
            'centers' => [
                ['t_ms' => 0, 'cx' => 0.5],
                ['t_ms' => 'nope'],            // missing/invalid cx
                ['cx' => 0.5],                 // missing t_ms
                'not-an-object',
            ],
        ])]);

        $out = $this->service($http)->sampleCenters($this->tempMedia(), 0, 1000);
        $this->assertCount(1, $out);
    }

    public function test_http_error_degrades_to_empty_centre_crop(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response('down', 500)]);

        // Must NOT throw — returns empty so the planner uses a centre crop.
        $out = $this->service($http)->sampleCenters($this->tempMedia(), 0, 1000);
        $this->assertSame([], $out);
    }

    public function test_missing_centers_key_returns_empty(): void
    {
        $http = new HttpFactory;
        $http->fake(['*' => $http->response(['ok' => true])]);

        $this->assertSame([], $this->service($http)->sampleCenters($this->tempMedia(), 0, 1000));
    }
}
