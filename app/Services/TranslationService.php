<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class TranslationService
{
    /**
     * Target language labels map.
     */
    public const LANGUAGES = [
        'original' => 'Asli (Original)',
        'id' => 'Indonesian (Bahasa Indonesia)',
        'en' => 'English (Inggris)',
        'es' => 'Spanish (Spanyol)',
        'ja' => 'Japanese (Jepang)',
        'de' => 'German (Jerman)',
    ];

    /**
     * Translate an array of word objects with timestamps.
     *
     * @param  array<int, array{word: string, start_ms: int, end_ms: int}>  $words
     * @param  string  $targetLang
     * @return array<int, array{word: string, start_ms: int, end_ms: int}>
     */
    public function translateWords(array $words, string $targetLang): array
    {
        if ($targetLang === 'original' || empty($words)) {
            return $words;
        }

        $fullText = implode(' ', array_column($words, 'word'));
        $cacheKey = 'trans_' . md5($fullText . '_' . $targetLang);

        $translatedText = Cache::remember($cacheKey, 86400, function () use ($fullText, $targetLang) {
            return $this->callTranslationLlm($fullText, $targetLang);
        });

        if (empty($translatedText)) {
            return $words;
        }

        // Split translated text into word tokens and map timestamps proportionally
        $translatedTokens = preg_split('/\s+/u', trim($translatedText)) ?: [];
        $origCount = count($words);
        $transCount = count($translatedTokens);

        if ($transCount === 0) {
            return $words;
        }

        $result = [];
        for ($i = 0; $i < $transCount; $i++) {
            $mappedOrigIndex = (int) floor(($i / $transCount) * $origCount);
            $origWord = $words[$mappedOrigIndex] ?? end($words);

            $result[] = [
                'word' => $translatedTokens[$i],
                'start_ms' => $origWord['start_ms'] ?? 0,
                'end_ms' => $origWord['end_ms'] ?? 0,
            ];
        }

        return $result;
    }

    private function callTranslationLlm(string $text, string $targetLang): string
    {
        $driver = (string) config('autoclip.llm.driver', 'agentrouter');
        $endpoint = (string) config('autoclip.llm.endpoint', 'https://agentrouter.org/v1');
        $apiKey = config('autoclip.llm.api_key');
        $model = (string) config('autoclip.llm.model', 'claude-opus-4-8');

        $targetLangName = self::LANGUAGES[$targetLang] ?? $targetLang;

        $systemPrompt = "You are a professional subtitle translator. Translate the given transcript text into {$targetLangName}. Return ONLY the direct translated text. Do not add quotes, notes, or explanations.";

        try {
            $url = rtrim($endpoint, '/') . '/chat/completions';
            $response = Http::timeout(10)
                ->withoutVerifying()
                ->withHeaders([
                    'User-Agent' => 'claude-cli/0.2.20 (external, cli)',
                    'Authorization' => $apiKey ? "Bearer {$apiKey}" : '',
                ])
                ->post($url, [
                    'model' => $model,
                    'messages' => [
                        ['role' => 'system', 'content' => $systemPrompt],
                        ['role' => 'user', 'content' => $text],
                    ],
                    'temperature' => 0.3,
                ]);

            if ($response->successful()) {
                $data = $response->json();
                return trim((string) ($data['choices'][0]['message']['content'] ?? ''));
            }
        } catch (Throwable) {}

        return '';
    }
}
