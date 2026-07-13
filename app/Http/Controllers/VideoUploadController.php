<?php

namespace App\Http\Controllers;

use App\Exceptions\IngestValidationException;
use App\Http\Requests\StoreVideoRequest;
use App\Jobs\IngestUrlJob;
use App\Services\VideoIngestService;
use App\Services\YtDlpService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VideoUploadController extends Controller
{
    public function store(StoreVideoRequest $request, VideoIngestService $ingest): JsonResponse
    {
        try {
            $video = $ingest->ingestUpload($request->file('video'));
        } catch (IngestValidationException $e) {
            // User-safe message; deeper cause is logged by the exception chain.
            return response()->json([
                'message' => $e->getMessage(),
            ], 422);
        }

        return response()->json([
            'id' => $video->id,
            'status' => $video->status,
            'duration_seconds' => $video->duration_seconds,
        ], 201);
    }

    /**
     * Ingest from a public video URL (spec 5.1). Validates the URL synchronously
     * (scheme + SSRF guard) then queues the download; the video row appears once
     * the download validates.
     */
    public function storeUrl(Request $request, YtDlpService $ytdlp, VideoIngestService $ingest): JsonResponse
    {
        $data = $request->validate(['url' => 'required|string|max:2048']);

        try {
            $ytdlp->assertSafeUrl($data['url']);
        } catch (IngestValidationException $e) {
            return response()->json(['message' => $e->getMessage()], 422);
        }

        // Create the tracked video row up front so failures are visible.
        $video = $ingest->createPendingUrlVideo($data['url']);
        IngestUrlJob::dispatch($video->id);

        return response()->json([
            'id' => $video->id,
            'status' => $video->status,
            'message' => 'URL accepted; downloading and processing in the background.',
        ], 202);
    }
}

