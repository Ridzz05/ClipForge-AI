<?php

namespace App\Livewire;

use App\Models\Asset;
use App\Models\Export;
use App\Services\FfprobeService;
use Illuminate\Support\Facades\Storage;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;
use Throwable;

#[Layout('components.layouts.app')]
class AssetLibrary extends Component
{
    use WithFileUploads;

    public $music;

    public $image;

    public function updatedMusic(): void
    {
        $this->resetValidation('music');
    }

    public function updatedImage(): void
    {
        $this->resetValidation('image');
    }

    public function uploadMusic(FfprobeService $ffprobe): void
    {
        $this->storeUpload(Asset::KIND_MUSIC, $ffprobe);
    }

    public function uploadImage(): void
    {
        $this->storeUpload(Asset::KIND_IMAGE);
    }

    public function deleteAsset(int $id): void
    {
        $asset = Asset::find($id);

        if (! $asset) {
            return;
        }

        // Exports keep rendering without music when the reference is cleared
        // (ReframeJob already logs and skips a missing file).
        if ($asset->kind === Asset::KIND_MUSIC) {
            Export::query()->where('music_asset_id', $asset->id)->update(['music_asset_id' => null]);
        }

        Storage::disk((string) config('autoclip.assets.disk'))->delete($asset->path);
        $asset->delete();

        $this->dispatch('toast', message: 'Aset dihapus.', type: 'success');
    }

    public function render()
    {
        $assets = Asset::query()->latest()->get();

        return view('livewire.asset-library', [
            'musicAssets' => $assets->where('kind', Asset::KIND_MUSIC)->values(),
            'imageAssets' => $assets->where('kind', Asset::KIND_IMAGE)->values(),
            'maxSizeKb' => (int) config('autoclip.assets.max_size_kb'),
            'musicMimes' => (array) config('autoclip.assets.music_mimes'),
            'imageMimes' => (array) config('autoclip.assets.image_mimes'),
        ]);
    }

    private function storeUpload(string $kind, ?FfprobeService $ffprobe = null): void
    {
        $field = $kind === Asset::KIND_MUSIC ? 'music' : 'image';
        $mimes = (array) config('autoclip.assets.'.$field.'_mimes');
        $maxKb = (int) config('autoclip.assets.max_size_kb');

        $this->validate([
            $field => ['required', 'file', 'mimes:'.implode(',', $mimes), 'max:'.$maxKb],
        ], [
            $field.'.required' => 'Pilih berkas dulu.',
            $field.'.mimes' => 'Format tidak didukung. Yang diterima: '.implode(', ', $mimes).'.',
            $field.'.max' => 'Berkas terlalu besar (maks '.round($maxKb / 1024).' MB).',
        ], [
            $field => $kind === Asset::KIND_MUSIC ? 'berkas musik' : 'berkas gambar',
        ]);

        $diskName = (string) config('autoclip.assets.disk');
        $disk = Storage::disk($diskName);
        $file = $this->{$field};

        // Read metadata first: Livewire's temp upload is MOVED by store(), so
        // getSize()/getMimeType() would fail on the vanished temp path.
        $name = $this->cleanName($file->getClientOriginalName());
        $mime = $file->getMimeType() ?: null;
        $size = (int) $file->getSize();

        $path = $file->store($kind === Asset::KIND_MUSIC ? 'assets/music' : 'assets/images', $diskName);

        $duration = null;
        if ($ffprobe !== null) {
            try {
                $duration = $ffprobe->durationSeconds($disk->path($path));
            } catch (Throwable) {
                $duration = null;
            }
        }

        Asset::create([
            'kind' => $kind,
            'name' => $name,
            'path' => $path,
            'mime' => $mime,
            'size_bytes' => $size,
            'duration_seconds' => $duration,
        ]);

        $this->reset($field);

        $this->dispatch(
            'toast',
            message: $kind === Asset::KIND_MUSIC ? 'Musik diunggah.' : 'Gambar diunggah.',
            type: 'success',
        );
    }

    /** Client filename reduced to a display name: no path, no extension, <= 80 chars. */
    private function cleanName(string $original): string
    {
        $base = basename(str_replace('\\', '/', $original));
        $base = pathinfo($base, PATHINFO_FILENAME);
        $base = trim((string) preg_replace('/[^\P{C}]+/u', ' ', $base));
        $base = trim((string) preg_replace('/\s+/u', ' ', $base));

        if ($base === '') {
            return 'Tanpa nama';
        }

        return mb_substr($base, 0, 80);
    }
}
