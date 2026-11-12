<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;

class OpenReelController
{
    public function serve(Request $request, ?string $path = null)
    {
        $root = realpath(resource_path('openreel'));
        abort_if($root === false, 404);

        $index = realpath($root.'/index.html');
        $wanted = ($path === null || $path === '')
            ? $index
            : realpath($root.'/'.ltrim($path, '/'));

        // Containment check: anything resolving outside the root falls back to the SPA index.
        $filePath = ($wanted !== false && str_starts_with($wanted, $root.DIRECTORY_SEPARATOR))
            ? $wanted
            : $index;

        abort_if($filePath === false || ! is_file($filePath), 404);

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
