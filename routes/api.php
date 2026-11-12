<?php

use App\Http\Controllers\ClipCandidateReviewController;
use App\Http\Controllers\ExportDownloadController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

// Single operator; access gated by BasicOperatorAuth (see config/autoclip.php `access`).
Route::middleware('throttle:60,1')->group(function () {
    // Stage 1 — Ingest (upload).
    Route::post('/videos', [VideoUploadController::class, 'store'])
        ->name('videos.store');

    // Stage 1 — Ingest via public video URL (yt-dlp download, queued).
    Route::post('/videos/url', [VideoUploadController::class, 'storeUrl'])
        ->name('videos.store-url');

    // Stage 4 — human review gate. Approving a candidate dispatches the reframe
    // /caption render; rejecting drops it.
    Route::post('/candidates/{candidate}/approve', [ClipCandidateReviewController::class, 'approve'])
        ->name('candidates.approve');
    Route::post('/candidates/{candidate}/reject', [ClipCandidateReviewController::class, 'reject'])
        ->name('candidates.reject');

    // Stage 5 — manual download of a finished export (Phase 1: no bot yet).
    Route::get('/exports/{export}/download', [ExportDownloadController::class, 'show'])
        ->name('exports.download');
});
