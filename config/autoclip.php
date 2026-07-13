<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Ingest limits (Security: resource exhaustion — spec section 6)
    |--------------------------------------------------------------------------
    | Hard caps enforced at upload before any expensive work is queued.
    */
    'ingest' => [
        // Max upload size in kilobytes (Laravel validation unit). 2 GB default.
        'max_size_kb' => (int) env('AUTOCLIP_MAX_SIZE_KB', 2 * 1024 * 1024),

        // Max source duration in seconds. Rejected via ffprobe before storing.
        'max_duration_seconds' => (int) env('AUTOCLIP_MAX_DURATION_SECONDS', 3 * 60 * 60),

        // Accepted container MIME types, verified by magic bytes (not extension).
        'allowed_mimes' => [
            'video/mp4',
            'video/quicktime',   // .mov
            'video/x-matroska',  // .mkv
            'video/webm',
            'video/x-msvideo',   // .avi
        ],

        // Storage disk (config/filesystems.php) for original uploads.
        'disk' => env('AUTOCLIP_INGEST_DISK', 'local'),
    ],

    /*
    |--------------------------------------------------------------------------
    | External binaries — invoked with argument arrays, never shell strings.
    |--------------------------------------------------------------------------
    */
    'ffmpeg_path' => env('AUTOCLIP_FFMPEG_PATH', 'ffmpeg'),
    'ffprobe_path' => env('AUTOCLIP_FFPROBE_PATH', 'ffprobe'),

    /*
    |--------------------------------------------------------------------------
    | Self-hosted services (spec section 3)
    |--------------------------------------------------------------------------
    */
    'whisper' => [
        'endpoint' => env('AUTOCLIP_WHISPER_ENDPOINT', 'http://127.0.0.1:9000'),
        'model' => env('AUTOCLIP_WHISPER_MODEL', 'small'),
    ],

    'ollama' => [
        'endpoint' => env('AUTOCLIP_OLLAMA_ENDPOINT', 'http://127.0.0.1:11434'),
        'model' => env('AUTOCLIP_OLLAMA_MODEL', 'qwen2.5:7b'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Per-job hard timeouts in seconds (resource exhaustion mitigation).
    |--------------------------------------------------------------------------
    */
    'timeouts' => [
        'transcribe' => (int) env('AUTOCLIP_TIMEOUT_TRANSCRIBE', 3600),
        'score' => (int) env('AUTOCLIP_TIMEOUT_SCORE', 900),
        'reframe' => (int) env('AUTOCLIP_TIMEOUT_REFRAME', 1800),
        'export' => (int) env('AUTOCLIP_TIMEOUT_EXPORT', 1800),
    ],
];
