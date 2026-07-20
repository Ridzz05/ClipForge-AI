<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class OpenReelController
{
    public function serve(Request $request, ?string $path = null)
    {
        $basePath = resource_path('openreel');

        // Target file path inside public/openreel
        $filePath = $path ? $basePath . '/' . ltrim($path, '/') : $basePath . '/index.html';

        if (!File::exists($filePath) || File::isDirectory($filePath)) {
            // Fallback to SPA index.html for client-side routing
            $filePath = $basePath . '/index.html';
        }

        $extension = pathinfo($filePath, PATHINFO_EXTENSION);
        $mimeTypes = [
            'html' => 'text/html; charset=UTF-8',
            'js'   => 'application/javascript',
            'css'  => 'text/css; charset=UTF-8',
            'wasm' => 'application/wasm',
            'json' => 'application/json',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'jpeg' => 'image/jpeg',
            'ico'  => 'image/x-icon',
            'woff' => 'font/woff',
            'woff2'=> 'font/woff2',
            'ttf'  => 'font/ttf',
        ];

        $contentType = $mimeTypes[$extension] ?? File::mimeType($filePath) ?? 'text/plain';

        return response()->file($filePath, [
            'Content-Type' => $contentType,
            'Cross-Origin-Opener-Policy' => 'same-origin',
            'Cross-Origin-Embedder-Policy' => 'require-corp',
        ]);
    }
}
