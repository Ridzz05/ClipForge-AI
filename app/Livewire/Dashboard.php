<?php

namespace App\Livewire;

use App\Exceptions\IngestValidationException;
use App\Models\Video;
use App\Services\VideoIngestService;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Validate;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Dashboard extends Component
{
    use WithFileUploads;

    /** Temp uploaded file (Livewire manages the temp storage). */
    #[Validate('required|file')]
    public $upload;

    public ?string $flash = null;

    public ?string $error = null;

    public function updatedUpload(): void
    {
        // Clear stale messages when a new file is chosen.
        $this->flash = null;
        $this->error = null;
    }

    public function save(VideoIngestService $ingest): void
    {
        $this->validate();

        // Livewire's TemporaryUploadedFile extends UploadedFile, so the same
        // ingest service (magic-byte + ffprobe validation) runs unchanged.
        /** @var UploadedFile $file */
        $file = $this->upload;

        try {
            $video = $ingest->ingestUpload($file);
        } catch (IngestValidationException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->reset('upload');
        $this->flash = "Video #{$video->id} diterima ({$video->duration_seconds}s). Pipeline dimulai.";
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
