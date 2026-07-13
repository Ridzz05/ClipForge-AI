<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Clip constraints (Stage 3 scoring / validation)
    |--------------------------------------------------------------------------
    | Enforced in HighlightSchema, independent of what the LLM proposes. Set a
    | hard minimum length here for campaigns that require it (e.g. clips must be
    | at least 10s). Values in milliseconds.
    */
    'clips' => [
        'min_ms' => (int) env('AUTOCLIP_CLIP_MIN_MS', 10_000),   // 10s campaign floor
        'max_ms' => (int) env('AUTOCLIP_CLIP_MAX_MS', 180_000),  // 3min ceiling
    ],

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
        // Some environments report the ISO Base Media container as
        // application/mp4 rather than video/mp4 — both are accepted.
        'allowed_mimes' => [
            'video/mp4',
            'application/mp4',
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

    'face' => [
        'endpoint' => env('AUTOCLIP_FACE_ENDPOINT', 'http://127.0.0.1:9100'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Render output spec (Stage 4/5)
    |--------------------------------------------------------------------------
    */
    'render' => [
        'width' => (int) env('AUTOCLIP_RENDER_WIDTH', 1080),
        'height' => (int) env('AUTOCLIP_RENDER_HEIGHT', 1920),
        'caption_style' => env('AUTOCLIP_CAPTION_STYLE', 'default'),
        // Default on-screen CTA burned onto every clip (campaign requirement:
        // clips must carry a call-to-action). Empty disables the overlay.
        // Per-export cta_text overrides this.
        'cta_text' => env('AUTOCLIP_CTA_TEXT', ''),
        // Optional watermark PNG (absolute path); null disables the overlay.
        'watermark_path' => env('AUTOCLIP_WATERMARK_PATH'),
        'disk' => env('AUTOCLIP_EXPORT_DISK', 'local'),
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
