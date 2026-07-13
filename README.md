# Auto-Clip AI

Self-hosted service that turns long-form video (podcasts, webinars, streams)
into short vertical captioned clips. See [`auto-clip-system-spec.md`](auto-clip-system-spec.md)
for the full specification.

Orchestrator: **Laravel 13 + SQLite (WAL)**. Heavy work runs as queued jobs so
video processing never blocks the request thread and retries independently.

## Pipeline

```
Ingest → Transcribe → Score highlights → Reframe & caption → Export & deliver
```

| Stage | Status | Notes |
|---|---|---|
| 1 Ingest | ✅ | Upload, magic-byte validation, ffprobe duration cap, UUID storage |
| 2 Transcribe | ✅ | faster-whisper service, word-level timestamps, crash-safe job |
| 3 Score highlights | ✅ | Ollama (qwen2.5), strict schema-validated JSON |
| 4 Reframe & caption | ✅ | MediaPipe pan + ffmpeg crop/scale, burned ASS captions |
| 5 Export & deliver | ✅ | Watermark overlay, render, manual download (Phase 1) |

## Prerequisites

- PHP 8.5+, Composer
- **ffmpeg / ffprobe** on PATH (ingest validation + Stages 4–5)
- Python 3.11 for the whisper service (`services/whisper/README.md`)
- Ollama for Stage 3 (once implemented)

## Setup

```bash
composer install
cp .env.example .env          # already present on this machine
php artisan key:generate
php artisan migrate            # creates videos, transcripts, queue tables, etc.
```

All Auto-Clip knobs live under `AUTOCLIP_*` in `.env` (caps, service
endpoints, per-job timeouts) — see `config/autoclip.php`.

## Running

Three processes:

```bash
# 1. Web/API
php artisan serve

# 2. Queue worker (processes pipeline jobs; retries on crash)
php artisan queue:work --tries=3

# 3. Whisper transcription service
cd services/whisper && python app.py   # see its README for install
```

## Usage (Phase 1)

```bash
# Ingest a video — returns {id, status, duration_seconds}
curl -F "video=@sample.mp4" http://127.0.0.1:8000/api/videos
```

This validates + stores the file and dispatches transcription. Later stages
pick up automatically as each job completes.

## Testing

```bash
php artisan test
```

Tests run against an in-memory SQLite DB and fake the external services
(whisper, ffprobe), so **no binaries or GPU are required** to run the suite.
