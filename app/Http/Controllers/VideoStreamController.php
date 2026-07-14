<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Streams a video's stored source file for the in-browser review player.
 * Phase 1 single-operator; the path is server-side only (never user input).
 */
class VideoStreamController extends Controller
{
    public function source(Video $video): BinaryFileResponse|JsonResponse
    {
        $disk = Storage::disk((string) config('autoclip.ingest.disk'));

        if ($video->storage_path === null || ! $disk->exists($video->storage_path)) {
            return response()->json(['message' => 'Source file missing.'], 404);
        }

        // Return a BinaryFileResponse to fully support HTTP range requests (seeking/206 Partial Content)
        return response()->file($disk->path($video->storage_path), [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
