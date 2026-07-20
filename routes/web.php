<?php

use App\Http\Controllers\OpenReelController;
use App\Http\Controllers\VideoStreamController;
use App\Livewire\ClipEditor;
use App\Livewire\Dashboard;
use App\Livewire\Exports;
use App\Livewire\ReviewVideo;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/exports', Exports::class)->name('exports');

Route::get('/videos/{video}/review', ReviewVideo::class)->name('videos.review');

Route::get('/candidates/{candidate}/edit', ClipEditor::class)->name('candidates.edit');

// Source stream for the in-browser review player.
Route::get('/videos/{video}/source', [VideoStreamController::class, 'source'])
    ->name('videos.source');

// OpenReel video editor suite (with COOP/COEP headers for SharedArrayBuffer / WebAssembly).
Route::get('/openreel/{path?}', [OpenReelController::class, 'serve'])
    ->where('path', '.*')
    ->name('openreel');

