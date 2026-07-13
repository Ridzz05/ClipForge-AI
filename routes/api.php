<?php

use App\Http\Controllers\ClipCandidateReviewController;
use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

// Stage 1 — Ingest (upload). Phase 1: single operator, no auth gate yet.
Route::post('/videos', [VideoUploadController::class, 'store'])
    ->name('videos.store');

// Stage 4 — human review gate. Approving a candidate dispatches the reframe
// /caption render; rejecting drops it.
Route::post('/candidates/{candidate}/approve', [ClipCandidateReviewController::class, 'approve'])
    ->name('candidates.approve');
Route::post('/candidates/{candidate}/reject', [ClipCandidateReviewController::class, 'reject'])
    ->name('candidates.reject');
