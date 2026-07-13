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

    public ?string $flash = null;

    /** Errors are scoped per form so one form's failure can't confuse the other. */
    public ?string $uploadError = null;

    public ?string $urlError = null;

    public function updatedUpload(): void
    {
        // Clear stale messages when a new file is chosen.
        $this->flash = null;
        $this->uploadError = null;
        $this->resetValidation();
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
            $video = $ingest->ingestUpload($file);
        } catch (IngestValidationException $e) {
            $this->uploadError = $e->getMessage();

            return;
        }

        $this->reset('upload');
        $this->resetValidation();
        // Clear the native <input type=file> so its stale filename doesn't
        // linger and cause a confusing "required" on the next submit.
        $this->dispatch('upload-cleared');

        $this->flash = "Video #{$video->id} diterima ({$video->duration_seconds}s). Pipeline dimulai.";
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
        $video = $ingest->createPendingUrlVideo($url);
        IngestUrlJob::dispatch($video->id);

        $this->reset('url');
        $this->flash = "URL diterima (video #{$video->id}). Mengunduh di background — pantau statusnya di daftar.";
    }

    public function render()
    {
        $videos = Video::query()
            ->withCount(['clipCandidates'])
            ->latest()
            ->limit(50)
            ->get();

        // Keep polling while any video is still processing; otherwise idle.
        $anyProcessing = $videos->contains(fn (Video $v) => $v->isProcessing());

        return view('livewire.dashboard', [
            'videos' => $videos,
            'poll' => $anyProcessing,
        ]);
    }
}
