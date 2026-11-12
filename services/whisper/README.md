# Whisper transcription service (Stage 2)

Self-hosted `faster-whisper` behind a small Flask HTTP endpoint. The Laravel
`WhisperService` talks to it over HTTP (`AUTOCLIP_WHISPER_ENDPOINT`, default
`http://127.0.0.1:9000`).

## Install

Requires **Python 3.11** and **ffmpeg** on PATH (faster-whisper decodes audio
via ffmpeg).

```bash
cd services/whisper
python -m venv .venv
# Windows:
.venv\Scripts\activate
# macOS/Linux:
source .venv/bin/activate

pip install -r requirements.txt
```

## Run

```bash
# defaults: model=small, device=cpu, compute=int8, port=9000
python app.py
```

Environment overrides:

| Var | Default | Notes |
|---|---|---|
| `WHISPER_MODEL` | `small` | `small` / `medium` / `large-v3` |
| `WHISPER_DEVICE` | `cpu` | `cuda` on a GPU box |
| `WHISPER_COMPUTE_TYPE` | `int8` | `float16` for GPU |
| `WHISPER_PORT` | `9000` | must match `AUTOCLIP_WHISPER_ENDPOINT` |

GPU (NVIDIA): `pip install nvidia-cublas-cu12 "nvidia-cudnn-cu12==9.*"` into the venv,
then run with `WHISPER_DEVICE=cuda WHISPER_COMPUTE_TYPE=float16`. `app.py` registers the
wheel DLL folders itself on Windows. Measured on an RTX 3050 6GB with `large-v3-turbo`:
60 s of audio in ~5 s (CPU int8 is roughly realtime).

First run downloads the model weights (~500 MB for `small`).

## Verify

```bash
curl http://127.0.0.1:9000/health
# {"status":"ok","default_model":"small","device":"cpu"}

curl -F "file=@sample.mp4" -F "word_timestamps=true" \
     http://127.0.0.1:9000/transcribe
```

## Contract

`POST /transcribe` (multipart) → JSON:

```json
{
  "language": "en",
  "text": "full transcript ...",
  "segments": [
    {"start": 0.0, "end": 3.2, "text": "hello world",
     "words": [{"word": "hello", "start": 0.0, "end": 0.5}]}
  ]
}
```

Times are float **seconds**; the PHP client converts to integer milliseconds.
