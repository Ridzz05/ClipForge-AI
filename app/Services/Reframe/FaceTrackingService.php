<?php

namespace App\Services\Reframe;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Client for the self-hosted MediaPipe face-tracking service (spec section
 * 5.4). Given a clip, it returns time-sampled normalized face centres that
 * ReframePlanner turns into a pan path.
 *
 * If the service is unavailable or returns nothing usable, this returns an
 * empty array — the planner then falls back to a static centre crop, so a
 * face-tracking outage degrades quality rather than failing the render.
 */
class FaceTrackingService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $endpoint,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(HttpFactory $http): self
    {
        return new self(
            http: $http,
            endpoint: rtrim((string) config('autoclip.face.endpoint'), '/'),
            timeout: (int) config('autoclip.timeouts.reframe'),
        );
    }

    /**
     * Sample face centres for a media file between start/end (ms, relative to
     * the source video).
     *
     * @return array<int, array{t_ms:int, cx:float}> empty ⇒ caller uses centre crop
     */
    public function sampleCenters(string $absolutePath, int $startMs, int $endMs): array
    {
        try {
            $response = $this->http
                ->baseUrl($this->endpoint)
                ->timeout($this->timeout)
                ->acceptJson()
                ->attach('file', fopen($absolutePath, 'r'), basename($absolutePath))
                ->post('/track', [
                    'start_ms' => (string) $startMs,
                    'end_ms' => (string) $endMs,
                ]);

            if (! $response->successful()) {
                throw new RuntimeException("face service HTTP {$response->status()}");
            }

            return $this->normalize($response->json());
        } catch (\Throwable $e) {
            // Degrade gracefully: no centres ⇒ static centre crop.
            Log::warning('Face tracking unavailable; falling back to centre crop', [
                'error' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * @param  mixed  $data  expected {"centers":[{"t_ms":int,"cx":float}, ...]}
     * @return array<int, array{t_ms:int, cx:float}>
     */
    private function normalize(mixed $data): array
    {
        $centers = is_array($data) ? ($data['centers'] ?? null) : null;
        if (! is_array($centers)) {
            return [];
        }

        $out = [];
        foreach ($centers as $c) {
            if (! is_array($c) || ! isset($c['t_ms'], $c['cx'])) {
                continue;
            }
            if (! is_numeric($c['t_ms']) || ! is_numeric($c['cx'])) {
                continue;
            }
            $out[] = [
                't_ms' => (int) $c['t_ms'],
                'cx' => max(0.0, min(1.0, (float) $c['cx'])),
            ];
        }

        return $out;
    }
}
