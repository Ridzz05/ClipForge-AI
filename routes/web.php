<?php

use App\Http\Controllers\VideoStreamController;
use App\Livewire\Dashboard;
use App\Livewire\Exports;
use App\Livewire\ReviewVideo;
use Illuminate\Support\Facades\Route;

Route::get('/', Dashboard::class)->name('dashboard');

Route::get('/exports', Exports::class)->name('exports');

Route::get('/videos/{video}/review', ReviewVideo::class)->name('videos.review');

// Source stream for the in-browser review player.
Route::get('/videos/{video}/source', [VideoStreamController::class, 'source'])
    ->name('videos.source');
