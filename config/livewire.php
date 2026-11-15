<?php

/*
|--------------------------------------------------------------------------
| Livewire overrides
|--------------------------------------------------------------------------
|
| Laravel merges package config with this file key-by-key at the TOP level,
| so overriding `temporary_file_upload` replaces the whole block. Keep every
| key to avoid silently losing Livewire defaults.
|
| Why: Livewire's temp-upload cap must match the real ingest cap
| (config/autoclip.php), or a 2 GB video is rejected at 12 MB before any
| component rule runs. The web launcher (`deploy/serve-web.ps1`) runs
| `config:cache`, which bypasses the package merge entirely and reads this
| file as-is — another reason to spell out the full block. Laravel still
| merges package defaults when a key is absent, but not the reverse.
|
*/

return [
    'temporary_file_upload' => [
        'disk' => env('LIVEWIRE_TEMPORARY_FILE_UPLOAD_DISK'),
        'rules' => [
            'required',
            'file',
            'max:'.max(
                (int) config('autoclip.ingest.max_size_kb'),
                (int) config('autoclip.assets.max_size_kb', 0),
            ),
        ],
        'directory' => null,
        'middleware' => null,
        'max_upload_time' => 5,
        'cleanup' => true,
        'preview_mimes' => [
            'png', 'gif', 'bmp', 'svg', 'wav', 'mp4',
            'mov', 'avi', 'wmv', 'mp3', 'm4a',
            'jpg', 'jpeg', 'mpga', 'webp', 'wma',
        ],
    ],
];
