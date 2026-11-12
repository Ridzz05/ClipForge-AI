<?php

namespace App\Http\Controllers;

use App\Models\Asset;
use App\Models\BrandKit;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams uploaded library files (music preview, image thumbnails) and the
 * brand logo. Paths come from the database, never from request input.
 */
class AssetFileController extends Controller
{
    public function asset(Asset $asset): BinaryFileResponse
    {
        $disk = Storage::disk((string) config('autoclip.assets.disk'));
        abort_unless($disk->exists($asset->path), 404);

        return response()->file($disk->path($asset->path), [
            'Content-Type' => $asset->mime ?: 'application/octet-stream',
        ]);
    }

    public function logo(): BinaryFileResponse
    {
        $kit = BrandKit::current();
        $disk = Storage::disk((string) config('autoclip.assets.disk'));
        abort_unless($kit->logo_path && $disk->exists($kit->logo_path), 404);

        return response()->file($disk->path($kit->logo_path));
    }
}
