<?php

namespace App\Http\Controllers;

use App\Models\Export;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stage 5 delivery for Phase 1: manual download (no bot yet, spec section 7).
 * Streams the finished export file from the export disk.
 */
class ExportDownloadController extends Controller
{
    public function show(Export $export): JsonResponse|StreamedResponse
    {
        if ($export->status !== Export::STATUS_RENDERED || $export->output_path === null) {
            return response()->json([
                'message' => 'Export is not ready for download.',
                'status' => $export->status,
            ], 409);
        }

        $disk = Storage::disk((string) config('autoclip.render.disk'));
        if (! $disk->exists($export->output_path)) {
            return response()->json(['message' => 'Export file is missing.'], 404);
        }

        // A stable, server-generated download name — never user-controlled.
        $downloadName = "clip-{$export->id}.mp4";

        return $disk->download($export->output_path, $downloadName, [
            'Content-Type' => 'video/mp4',
        ]);
    }
}
