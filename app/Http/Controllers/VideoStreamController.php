<?php

namespace App\Http\Controllers;

use App\Models\Video;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Streams a video's stored source file for the in-browser review player.
 * Phase 1 single-operator; the path is server-side only (never user input).
 */
class VideoStreamController extends Controller
{
    public function source(Video $video): StreamedResponse|JsonResponse
    {
        $disk = Storage::disk((string) config('autoclip.ingest.disk'));

        if ($video->storage_path === null || ! $disk->exists($video->storage_path)) {
            return response()->json(['message' => 'Source file missing.'], 404);
        }

        // Inline so the browser <video> element can play it directly.
        return $disk->response($video->storage_path, null, [
            'Content-Type' => 'video/mp4',
            'Accept-Ranges' => 'bytes',
        ]);
    }
}
