# Face-tracking service (Stage 4)

Self-hosted MediaPipe face detection behind a small Flask endpoint. The Laravel
`FaceTrackingService` calls it to get time-sampled face centres, which
`ReframePlanner` turns into a smoothed 9:16 pan path.

**Optional by design:** if this service is down, Laravel logs a warning and
falls back to a static centre crop. It improves reframing quality; it is not
required for a render to succeed.

## Install

Requires **Python 3.11**. MediaPipe wheels exist for 3.11 (not yet all newer
versions), so use 3.11 specifically.

```bash
cd services/reframe
python -m venv .venv
# Windows:
.venv\Scripts\activate
# macOS/Linux:
source .venv/bin/activate

pip install -r requirements.txt
```

## Run

```bash
# defaults: port=9100, sample rate=4 Hz
python app.py
```

| Var | Default | Notes |
|---|---|---|
| `FACE_PORT` | `9100` | must match `AUTOCLIP_FACE_ENDPOINT` |
| `FACE_SAMPLE_HZ` | `4` | face-centre samples per second of clip |

## Verify

```bash
curl http://127.0.0.1:9100/health
# {"status":"ok","sample_hz":4.0}

curl -F "file=@clip.mp4" -F "start_ms=0" -F "end_ms=30000" \
     http://127.0.0.1:9100/track
```

## Contract

`POST /track` (multipart: `file`, `start_ms`, `end_ms`) → JSON:

```json
{"centers": [{"t_ms": 0, "cx": 0.52}, {"t_ms": 250, "cx": 0.55}]}
```

`cx` is the primary face's horizontal centre normalised to `0..1` across the
frame width. An empty `centers` array is valid and yields a centre crop.
