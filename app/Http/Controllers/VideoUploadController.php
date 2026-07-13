<?php

namespace App\Http\Controllers;

use App\Exceptions\IngestValidationException;
use App\Http\Requests\StoreVideoRequest;
use App\Services\VideoIngestService;
use Illuminate\Http\JsonResponse;

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
}
