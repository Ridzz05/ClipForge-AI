<?php

namespace App\Services;

use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\PendingRequest;
use RuntimeException;

/**
 * Stage 2 (spec section 5.2). Talks to the self-hosted faster-whisper service
 * over HTTP so transcription can be scaled/swapped independently of Laravel.
 *
 * The service is expected to accept a multipart upload of the audio/video file
 * and return word-level timestamps. We normalise its response into a stable
 * shape the rest of the app depends on, so a future swap of the Python side
 * only touches this class.
 *
 * Expected response JSON (word_timestamps=true):
 *   {
 *     "language": "en",
 *     "text": "full transcript ...",
 *     "segments": [
 *       {"start": 0.0, "end": 3.2, "text": "hello world",
 *        "words": [{"word": "hello", "start": 0.0, "end": 0.5}, ...]}
 *     ]
 *   }
 */
class WhisperService
{
    public function __construct(
        private readonly HttpFactory $http,
        private readonly string $endpoint,
        private readonly string $model,
        private readonly int $timeout,
    ) {}

    public static function fromConfig(HttpFactory $http): self
    {
        return new self(
            http: $http,
            endpoint: rtrim((string) config('autoclip.whisper.endpoint'), '/'),
            model: (string) config('autoclip.whisper.model'),
            timeout: (int) config('autoclip.timeouts.transcribe'),
        );
    }

    /**
     * Transcribe a media file at an absolute path.
     *
     * @return array{language: ?string, full_text: string, segments: array<int, array{
     *     start_ms: int, end_ms: int, text: string,
     *     words: array<int, array{word: string, start_ms: int, end_ms: int}>
     * }>}
     */
    public function transcribe(string $absolutePath): array
    {
        if (! is_file($absolutePath)) {
            throw new RuntimeException("Media file not found for transcription: {$absolutePath}");
        }

        $response = $this->client()
            ->attach('file', file_get_contents($absolutePath), basename($absolutePath))
            ->post('/transcribe', [
                'model' => $this->model,
                'word_timestamps' => 'true',
            ]);

        if (! $response->successful()) {
            throw new RuntimeException(
                "Whisper service returned HTTP {$response->status()}: ".$response->body()
            );
        }

        $data = $response->json();
        if (! is_array($data) || ! isset($data['segments']) || ! is_array($data['segments'])) {
            throw new RuntimeException('Whisper response missing a segments array.');
        }

        return $this->normalize($data);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{language: ?string, full_text: string, segments: array<int, mixed>}
     */
    private function normalize(array $data): array
    {
        $segments = [];

        foreach ($data['segments'] as $seg) {
            if (! is_array($seg)) {
                continue;
            }

            $words = [];
            foreach ($seg['words'] ?? [] as $w) {
                if (! is_array($w) || ! isset($w['word'])) {
                    continue;
                }
                $words[] = [
                    'word' => (string) $w['word'],
                    'start_ms' => $this->toMs($w['start'] ?? 0),
                    'end_ms' => $this->toMs($w['end'] ?? 0),
                ];
            }

            $segments[] = [
                'start_ms' => $this->toMs($seg['start'] ?? 0),
                'end_ms' => $this->toMs($seg['end'] ?? 0),
                'text' => trim((string) ($seg['text'] ?? '')),
                'words' => $words,
            ];
        }

        $fullText = isset($data['text']) && is_string($data['text'])
            ? trim($data['text'])
            : trim(implode(' ', array_column($segments, 'text')));

        return [
            'language' => isset($data['language']) ? (string) $data['language'] : null,
            'full_text' => $fullText,
            'segments' => $segments,
        ];
    }

    /** Whisper emits float seconds; we persist integer milliseconds. */
    private function toMs(mixed $seconds): int
    {
        return (int) round(((float) $seconds) * 1000);
    }

    private function client(): PendingRequest
    {
        return $this->http
            ->baseUrl($this->endpoint)
            ->timeout($this->timeout)
            ->acceptJson();
    }
}
