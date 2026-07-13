# Auto-Clip AI — System Specification

**Status:** Draft v1 — for review before implementation
**Owner:** Rizki (Code Reviewer / Architect)
**Implementer:** AI coding agent (Claude Code)
**Related system:** Reuses the orchestration pattern from `auto-deployment-system-spec.md`
 (Laravel + Ollama + queue workers)

---

## 1. Purpose

A general-purpose service that takes a long-form video (podcast, webinar,
raw UGC footage, livestream recording) and automatically produces short,
vertical, captioned, watermark-ready clips suitable for Reels / Shorts /
TikTok — comparable in function to OpusClip/Vizard, but self-hosted and
tailored to our own branding/workflow needs.

**Out of scope for v1:** direct auto-posting to social platforms, analytics
dashboard, multi-tenant billing. These are Phase 2+.

---

## 2. Pipeline Overview

Five stages, each a queued job so long-running video processing doesn't
block the request thread and can retry independently:

```
Ingest → Transcribe → Score highlights → Reframe & caption → Export & deliver
```

| Stage | Responsibility | Key tech |
|---|---|---|
| Ingest | Accept video via upload, URL, or bot message; validate; store | Laravel controller + queue |
| Transcribe | Speech-to-text with word-level timestamps | faster-whisper (self-hosted) |
| Score highlights | Identify and rank candidate clip time-ranges | Hermes via Ollama (self-hosted LLM) |
| Reframe & caption | Crop to 9:16 tracking the speaker, burn in captions | MediaPipe + ffmpeg |
| Export & deliver | Apply watermark, render final files, notify user | ffmpeg + Telegram/WhatsApp webhook |

---

## 3. Tech Stack (consistent with existing stack)

- **Orchestrator:** Laravel 11 — same framework and queue-worker pattern as
  the auto-deployment system.
- **Database:** SQLite in WAL mode — consistent with the prior architectural
  decision, revisit only if concurrent write volume becomes a bottleneck.
- **LLM:** Hermes via Ollama, self-hosted, separate service — same pattern
  as the auto-deployment system's intent parser.
- **Transcription:** `faster-whisper`, self-hosted as its own service
  (CPU is workable for `small`/`medium` models; GPU recommended for `large-v3`
  if volume grows).
- **Computer vision:** MediaPipe for face/subject tracking — same library
  already used in the gym posture-analysis project, so no new dependency
  class to learn.
- **Video processing:** ffmpeg, invoked with fixed argument arrays (never
  shell-interpolated strings — see Security section).
- **Delivery:** reuse the existing Telegram/WhatsApp webhook bot for
  ingestion triggers and completion notifications.

**Environments:**
- **Development / testing:** local laptop (Ryzen 5 6600H, CPU-only inference
  — the integrated Radeon 660M is not on ROCm's officially supported list,
  so treat it as CPU-only unless the Vulkan backend happens to pick it up).
  Sufficient for iterating on prompts, transcription accuracy, and the
  reframe/caption logic before touching the VPS.
- **Production:** the existing VPS, unchanged from the auto-deployment
  system's target host. Since all stages run through the async job queue,
  production throughput is not latency-sensitive the way a chat interface
  would be — CPU-only inference on the VPS remains acceptable at launch.

**Self-hosted model footprint (disk / RAM, Q4_K_M quantization):**

| Model | Role | Download size | RAM to run |
|---|---|---|---|
| `qwen2.5:7b` | Highlight scoring (default pick) | ~4.7 GB | ~5.5 GB |
| `llama3.2:3b` | Highlight scoring (lighter fallback) | ~2 GB | ~3 GB |
| `faster-whisper small` | Transcription | ~500 MB | ~1-2 GB |
| `faster-whisper medium` | Transcription (better accuracy) | ~1.5 GB | ~3-5 GB |
| MediaPipe face detection | Reframe/tracking | a few MB | negligible |

Total realistic footprint for the `qwen2.5:7b` + `faster-whisper small`
combo: **roughly 5-6 GB on disk, ~7-8 GB RAM at peak** when both are loaded
— comfortable on both the dev laptop and the VPS as long as the VPS has
16 GB RAM or more headroom alongside the other services already running
there (Laravel, Ollama for the auto-deployment system, etc.).

---

## 4. Data Model

```
videos
  id, source_type (upload|url|bot), source_ref, status, duration_seconds,
  storage_path, created_at

transcripts
  id, video_id (FK), full_text, language, created_at

transcript_segments
  id, transcript_id (FK), start_ms, end_ms, text, speaker_label (nullable)

clip_candidates
  id, video_id (FK), start_ms, end_ms, hook_score (0-100),
  score_rationale (text, from LLM), status (pending|approved|rejected|exported)

exports
  id, clip_candidate_id (FK), aspect_ratio, output_path, watermark_applied (bool),
  caption_style, rendered_at

jobs
  id, video_id (FK), stage, status (queued|running|failed|done),
  attempts, last_error, updated_at
```

`clip_candidates.status` defaults to `pending` — a human review gate before
a clip moves to export, matching the existing "Rizki as code/output
reviewer" role rather than fully autonomous publishing.

---

## 5. Stage Detail

### 5.1 Ingest
- Accept: direct upload, public video URL, or a video forwarded through the
  Telegram/WhatsApp bot.
- Validate: file type via magic bytes (not extension), max duration/size
  cap, reject corrupt files early via `ffprobe`.
- Store original in object storage or VPS disk under a job-scoped path —
  never a user-controlled filename.

### 5.2 Transcribe
- Run `faster-whisper` as its own service (mirrors the Ollama-as-separate-
  service decision) so it can be scaled or swapped independently.
- Persist word-level timestamps — required later for accurate caption
  burn-in and highlight boundary snapping.

### 5.3 Score highlights
- Feed transcript segments (batched, not the full transcript in one call)
  to the LLM with a strict output schema: an array of
  `{start_ms, end_ms, hook_score, rationale}`.
- Validate the LLM's JSON against a schema before writing to the DB —
  never trust it as executable instruction, only as data (see Security).
- Optional heuristic layer to complement the LLM: audio RMS peak
  detection or scene-cut detection (`PySceneDetect`) to catch highlights
  the transcript alone might miss (e.g. a visual reaction with no dialogue).

### 5.4 Reframe & caption
- MediaPipe face detection per candidate clip → determine a smooth pan/crop
  path to 9:16 that keeps the speaker centered; static center-crop fallback
  if no face is detected.
- Burn in captions from word-level timestamps via ffmpeg `ass`/`drawtext`
  filters; caption style should be a configurable template, not hardcoded.

### 5.5 Export & deliver
- Apply watermark overlay (reuse the same ffmpeg overlay logic already
  validated for the Yeet Casino campaign tooling).
- Render at target spec (duration, aspect ratio, resolution) per platform.
- Notify via the existing bot with a preview + approve/reject action before
  the clip is marked ready to post.

---

## 6. Security & Risk Notes

This system inherits the same risk category as the auto-deployment
pipeline — untrusted input flowing into an LLM and then into file/process
operations — so the same discipline applies:

- **Prompt injection surface:** transcript text is effectively
  user-controlled input to the LLM (anyone can put arbitrary spoken content
  in a video). Constrain the LLM call to structured JSON output only, never
  let its output construct shell commands or file paths directly.
- **Command injection:** all ffmpeg/ffprobe invocations must use argument
  arrays, never string concatenation into a shell call. Filenames must be
  generated server-side (e.g. UUIDs), never taken from user input.
- **Resource exhaustion:** cap max input video duration/size at ingest;
  set hard timeouts per queue job; limit concurrent transcription/render
  jobs so one large upload can't starve the queue.
- **Storage growth:** define a retention policy for original uploads and
  rejected candidates (e.g. auto-delete after N days) — video storage
  grows fast and silently.

---

## 7. Phased Rollout

**Phase 1 (MVP):**
Ingest (upload only) → transcribe → LLM scoring → manual review of
candidates → reframe/caption/watermark → manual download. No bot
integration, no auto-notify yet.

**Phase 2:**
Bot-based ingestion and delivery, heuristic highlight layer, caption style
templates, retention/cleanup job.

**Phase 3 (optional):**
Direct posting integration (IG/YouTube APIs), scheduling, basic analytics
on which exported clips got used.

---

## 8. Definition of Done (Phase 1)

- [x] Upload endpoint validates and stores video, creates `videos` row
      — `VideoIngestService`, `VideoIngestTest`
- [x] Transcription job produces word-level timestamped transcript
      — `TranscribeJob` + `services/whisper`, `TranscribeTest`
- [x] Scoring job returns validated, schema-checked clip candidates
      — `ScoreHighlightsJob` + `HighlightSchema`, `ScoreHighlightsTest`
- [x] Reframe/caption/watermark job produces a 9:16 exported file per
      approved candidate — `ReframeJob` + `ExportDeliverJob`,
      `ReframeJobTest` / `ExportDeliverTest`
- [x] All ffmpeg calls use argument arrays, verified with a malicious
      filename test case — `FfmpegService`, `ReframeCommandBuilderTest` +
      `WatermarkCommandBuilderTest` (hostile-path cases)
- [x] Job queue survives a worker crash mid-stage (retry, not data loss)
      — all jobs `tries=3` + idempotent transactional writes; asserted in
      `TranscribeTest` (idempotency, atomic rollback, retry-config)
- [x] Max duration/size limits enforced and tested
      — `config/autoclip.php` caps, `VideoIngestTest` (over-duration reject)

**Phase 1 status:** complete. Full test suite: 83 passing. External binaries
(ffmpeg) and self-hosted services (whisper, ollama, MediaPipe) are faked in
tests, so the suite runs with no runtime deps; see each `services/*/README.md`
to run them for real.

---

## 9. Open Questions

- Target concurrency: how many videos/day at launch? Determines whether
  CPU-only Whisper is sufficient or a GPU box is needed.
- Storage: VPS local disk vs. S3-compatible object storage — depends on
  expected volume and budget.
- Review UI: CLI/manual file review acceptable for Phase 1, or is a minimal
  web review UI needed sooner than Phase 2?

**Resolved:** dev/testing happens on the local laptop (CPU-only); production
deploys to the existing VPS. See Environments under section 3.
