<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Throwable;

class SystemHealthService
{
    /**
     * Check status of Whisper, Face Tracker, LLM, and Queue Worker.
     *
     * @return array{whisper: bool, face: bool, llm: bool, queue: bool}
     */
    public function getStatuses(): array
    {
        return Cache::remember('service_statuses', 5, function (): array {
            return [
                'whisper' => $this->checkWhisper(),
                'face' => $this->checkFaceTracker(),
                'llm' => $this->checkLlm(),
                'queue' => $this->checkQueueWorker(),
            ];
        });
    }

    public function clearStatusCache(): void
    {
        Cache::forget('service_statuses');
    }

    public function restartQueue(): void
    {
        $this->clearStatusCache();
        Artisan::call('queue:restart');
    }

    public function wakeUpQueue(): bool
    {
        $this->clearStatusCache();
        $statuses = $this->getStatuses();
        if ($statuses['queue']) {
            return true;
        }

        try {
            $phpBinary = '"' . PHP_BINARY . '"';
            $artisanPath = '"' . base_path('artisan') . '"';

            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                $command = "start /B \"\" {$phpBinary} {$artisanPath} queue:work --tries=3 --timeout=3600";
                @pclose(@popen($command, 'r'));
            } else {
                $command = "{$phpBinary} {$artisanPath} queue:work --tries=3 --timeout=3600 > /dev/null 2>&1 &";
                @exec($command);
            }
            return true;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkWhisper(): bool
    {
        $whisperUrl = (string) config('autoclip.whisper.endpoint', 'http://127.0.0.1:9000');
        try {
            return Http::timeout(1)->withoutVerifying()->get($whisperUrl . '/health')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function checkFaceTracker(): bool
    {
        $faceUrl = (string) config('autoclip.face.endpoint', 'http://127.0.0.1:9100');
        try {
            return Http::timeout(1)->withoutVerifying()->get($faceUrl . '/health')->successful();
        } catch (Throwable) {
            return false;
        }
    }

    private function checkLlm(): bool
    {
        $llmDriver = (string) config('autoclip.llm.driver', 'ollama');
        $llmEndpoint = (string) config('autoclip.llm.endpoint', 'http://127.0.0.1:11434');

        try {
            if ($llmDriver === 'ollama') {
                return Http::timeout(1)->withoutVerifying()->get($llmEndpoint)->successful();
            }
            return Http::timeout(2)->withoutVerifying()->get($llmEndpoint)->status() !== 0;
        } catch (Throwable) {
            return false;
        }
    }

    private function checkQueueWorker(): bool
    {
        try {
            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                @exec("wmic process where \"CommandLine like '%queue:work%' and name='php.exe'\" get ProcessId 2>&1", $winOutput);
                foreach ($winOutput as $line) {
                    if (is_numeric(trim($line))) {
                        return true;
                    }
                }
            } else {
                @exec("pgrep -f 'queue:work' 2>&1", $pgrepOutput);
                return !empty($pgrepOutput);
            }
        } catch (Throwable) {}

        return false;
    }
}
