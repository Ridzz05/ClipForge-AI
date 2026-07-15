<?php

namespace App\Livewire;

use App\Exceptions\IngestValidationException;
use App\Jobs\IngestUrlJob;
use App\Models\Video;
use App\Services\VideoIngestService;
use App\Services\YtDlpService;
use Illuminate\Http\UploadedFile;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    use WithFileUploads;

    /**
     * Temp uploaded file (Livewire manages the temp storage). No #[Validate]
     * attribute on purpose: that enables real-time auto-validation, which made
     * the "upload required" error leak onto the page while the operator was
     * using the separate URL form. We validate explicitly inside save() only.
     */
    public $upload;

    /** Public video URL to ingest via yt-dlp. */
    public string $url = '';

    /** Video resolution limit for yt-dlp. */
    public string $resolution = 'best';

    public bool $autoClip = false;

    public ?string $flash = null;

    /** Errors are scoped per form so one form's failure can't confuse the other. */
    public ?string $uploadError = null;

    public ?string $urlError = null;

    public array $statuses = [];

    public ?int $selectedVideoId = null;

    public bool $showStatusModal = false;

    public function updatedUpload(): void
    {
        // Clear stale messages when a new file is chosen.
        $this->flash = null;
        $this->uploadError = null;
        $this->resetValidation();
    }

    public function restartQueue(): void
    {
        \Illuminate\Support\Facades\Cache::forget('service_statuses');
        \Illuminate\Support\Facades\Artisan::call('queue:restart');
        $this->dispatch('toast', message: "Sinyal restart antrean dikirim ke Queue Worker.", type: 'success');
    }

    public function wakeUpQueue(): void
    {
        \Illuminate\Support\Facades\Cache::forget('service_statuses');
        $statuses = $this->checkServiceStatuses();
        if ($statuses['queue']) {
            $this->dispatch('toast', message: "Antrean (Queue Worker) sudah berjalan aktif.", type: 'info');
            return;
        }

        try {
            $phpBinary = '"' . PHP_BINARY . '"';
            $artisanPath = '"' . base_path('artisan') . '"';

            if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                // Window start needs empty title parameter when executable is quoted
                $command = "start /B \"\" {$phpBinary} {$artisanPath} queue:work --tries=3";
                pclose(popen($command, "r"));
            } else {
                $command = "{$phpBinary} {$artisanPath} queue:work --tries=3 > /dev/null 2>&1 &";
                exec($command);
            }
            $this->dispatch('toast', message: "Antrean (Queue Worker) berhasil dibangunkan di latar belakang!", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: "Gagal membangunkan antrean: " . $e->getMessage(), type: 'error');
        }
    }

    public function save(VideoIngestService $ingest): void
    {
        $this->flash = null;
        $this->uploadError = null;

        // Validate ONLY the upload field, and only on this action.
        $this->validate(
            ['upload' => 'required|file'],
            ['upload.required' => 'Pilih file video dulu (atau tunggu unggahan selesai).'],
        );

        // Livewire's TemporaryUploadedFile extends UploadedFile, so the same
        // ingest service (magic-byte + ffprobe validation) runs unchanged.
        /** @var UploadedFile $file */
        $file = $this->upload;

        try {
            $video = $ingest->ingestUpload($file, $this->autoClip);
        } catch (IngestValidationException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset('upload');
        $this->resetValidation();
        // Clear the native <input type=file> so its stale filename doesn't
        // linger and cause a confusing "required" on the next submit.
        $this->dispatch('upload-cleared');

        $this->dispatch('toast', message: "Video #{$video->id} diterima ({$video->duration_seconds}s). Pipeline dimulai.", type: 'success');
    }

    public function ingestUrl(YtDlpService $ytdlp, VideoIngestService $ingest): void
    {
        $this->flash = null;
        $this->urlError = null;
        // Don't let a lingering upload-form validation error bleed in here.
        $this->resetValidation();

        $url = trim($this->url);
        if ($url === '') {
            $this->urlError = 'Masukkan URL video.';

            return;
        }

        // Validate up front so the operator gets immediate feedback on a bad or
        // unsafe URL, before it's queued.
        try {
            $ytdlp->assertSafeUrl($url);
        } catch (IngestValidationException $e) {
            $this->urlError = $e->getMessage();

            return;
        }

        // Create the video row now (status=downloading) so it appears in the
        // list immediately; the slow download runs on the queue.
        $video = $ingest->createPendingUrlVideo($url, $this->autoClip);
        IngestUrlJob::dispatch($video->id, $this->resolution);

        $this->reset('url');
        $this->dispatch('toast', message: "URL diterima (video #{$video->id}). Mengunduh di background.", type: 'success');
    }

    public function deleteVideo(int $id): void
    {
        $video = Video::find($id);
        if (!$video) {
            return;
        }

        try {
            // 1. Delete source video folder
            if ($video->storage_path) {
                $dir = dirname($video->storage_path);
                \Illuminate\Support\Facades\Storage::disk((string) config('autoclip.ingest.disk'))->deleteDirectory($dir);
            } else {
                // If it is a url-ingest video that hasn't finished, delete job dir
                \Illuminate\Support\Facades\Storage::disk((string) config('autoclip.ingest.disk'))->deleteDirectory("videos/url-{$video->id}");
            }

            // 2. Delete exports folders & files
            foreach ($video->clipCandidates as $candidate) {
                foreach ($candidate->exports as $export) {
                    if ($export->output_path) {
                        \Illuminate\Support\Facades\Storage::disk((string) config('autoclip.render.disk'))->deleteDirectory("exports/{$export->id}");
                    }
                    $export->delete();
                }
                $candidate->delete();
            }

            // 3. Delete pipelines and transcript
            $video->pipelineJobs()->delete();
            if ($video->transcript) {
                $video->transcript->segments()->delete();
                $video->transcript->delete();
            }

            // 4. Delete the video row itself
            $video->delete();

            $this->dispatch('toast', message: "Video #{$id} dan seluruh berkas/kandidat terkait berhasil dihapus.", type: 'success');
        } catch (\Throwable $e) {
            $this->dispatch('toast', message: "Gagal menghapus video: " . $e->getMessage(), type: 'error');
        }
    }

    public function showStatusModal(int $id): void
    {
        $this->selectedVideoId = $id;
        $this->showStatusModal = true;
    }

    public function closeStatusModal(): void
    {
        $this->selectedVideoId = null;
        $this->showStatusModal = false;
    }

    private function checkServiceStatuses(): array
    {
        return \Illuminate\Support\Facades\Cache::remember('service_statuses', 5, function () {
            $whisperUrl = (string) config('autoclip.whisper.endpoint', 'http://127.0.0.1:9000');
            $whisperOnline = false;
            try {
                $whisperOnline = \Illuminate\Support\Facades\Http::timeout(1)->withoutVerifying()->get($whisperUrl . '/health')->successful();
            } catch (\Throwable $e) {}

            $faceUrl = (string) config('autoclip.face.endpoint', 'http://127.0.0.1:9100');
            $faceOnline = false;
            try {
                $faceOnline = \Illuminate\Support\Facades\Http::timeout(1)->withoutVerifying()->get($faceUrl . '/health')->successful();
            } catch (\Throwable $e) {}

            $llmDriver = (string) config('autoclip.llm.driver', 'ollama');
            $llmEndpoint = (string) config('autoclip.llm.endpoint', 'http://127.0.0.1:11434');
            $llmOnline = false;
            try {
                if ($llmDriver === 'ollama') {
                    $llmOnline = \Illuminate\Support\Facades\Http::timeout(1)->withoutVerifying()->get($llmEndpoint)->successful();
                } else {
                    // Cloud/router check
                    $llmOnline = \Illuminate\Support\Facades\Http::timeout(2)->withoutVerifying()->get($llmEndpoint)->status() !== 0;
                }
            } catch (\Throwable $e) {}

            $queueOnline = false;
            try {
                if (strncasecmp(PHP_OS, 'WIN', 3) === 0) {
                    @exec("wmic process where \"CommandLine like '%queue:work%' and name='php.exe'\" get ProcessId 2>&1", $winOutput);
                    foreach ($winOutput as $line) {
                        if (is_numeric(trim($line))) {
                            $queueOnline = true;
                            break;
                        }
                    }
                } else {
                    @exec('pgrep -f "queue:work"', $unixOutput);
                    $queueOnline = count($unixOutput) > 0;
                }
            } catch (\Throwable $e) {}

            return [
                'whisper' => $whisperOnline,
                'face' => $faceOnline,
                'llm' => $llmOnline,
                'llm_driver' => $llmDriver,
                'queue' => $queueOnline,
            ];
        });
    }

    public function render()
    {
        $videos = Video::query()
            ->withCount(['clipCandidates'])
            ->latest()
            ->limit(50)
            ->get();

        $this->statuses = $this->checkServiceStatuses();

        // Keep polling while any video is still processing OR if the status modal is open!
        $anyProcessing = $videos->contains(fn (Video $v) => $v->isProcessing()) || $this->showStatusModal;

        $selectedVideo = $this->selectedVideoId 
            ? Video::with(['pipelineJobs'])->find($this->selectedVideoId) 
            : null;

        return view('livewire.dashboard', [
            'videos' => $videos,
            'poll' => $anyProcessing,
            'selectedVideo' => $selectedVideo,
        ]);
    }
}
