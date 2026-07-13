<?php

use App\Http\Controllers\VideoUploadController;
use Illuminate\Support\Facades\Route;

// Stage 1 — Ingest (upload). Phase 1: single operator, no auth gate yet.
Route::post('/videos', [VideoUploadController::class, 'store'])
    ->name('videos.store');
