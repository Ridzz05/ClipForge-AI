"""
faster-whisper HTTP service for Auto-Clip (Stage 2, spec section 5.2).

Runs as its own process so transcription can be scaled/swapped independently of
the Laravel orchestrator. Exposes a single endpoint the WhisperService PHP
client calls:

    POST /transcribe   (multipart: file=<media>, model=<name>, word_timestamps=true)
    ->  { "language", "text", "segments": [{start, end, text, words:[{word,start,end}]}] }

Times are float seconds; the PHP side converts to integer milliseconds.
"""
import os
import tempfile

from faster_whisper import WhisperModel
from flask import Flask, jsonify, request

app = Flask(__name__)

# Model is loaded once and reused. CPU int8 is the dev/VPS default (spec 3);
# override via env for a GPU box.
_MODEL_NAME = os.environ.get("WHISPER_MODEL", "small")
_DEVICE = os.environ.get("WHISPER_DEVICE", "cpu")
_COMPUTE_TYPE = os.environ.get("WHISPER_COMPUTE_TYPE", "int8")
_CPU_THREADS = int(os.environ.get("WHISPER_CPU_THREADS", str(os.cpu_count() or 4)))
_BEAM_SIZE = int(os.environ.get("WHISPER_BEAM_SIZE", "5"))

_model_cache: dict[str, WhisperModel] = {}


def get_model(name: str) -> WhisperModel:
    if name not in _model_cache:
        _model_cache[name] = WhisperModel(
            name,
            device=_DEVICE,
            compute_type=_COMPUTE_TYPE,
            cpu_threads=_CPU_THREADS,
            num_workers=2,
        )
    return _model_cache[name]


@app.get("/health")
def health():
    return jsonify(
        status="ok",
        default_model=_MODEL_NAME,
        device=_DEVICE,
        cpu_threads=_CPU_THREADS,
        beam_size=_BEAM_SIZE,
    )


@app.post("/transcribe")
def transcribe():
    if "file" not in request.files:
        return jsonify(error="missing 'file'"), 400

    model_name = request.form.get("model", _MODEL_NAME)
    want_words = request.form.get("word_timestamps", "true").lower() == "true"
    beam_size = int(request.form.get("beam_size", _BEAM_SIZE))

    upload = request.files["file"]
    suffix = os.path.splitext(upload.filename or "")[1] or ".bin"

    # Persist to a temp file: faster-whisper reads a path, not a stream.
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        upload.save(tmp.name)
        tmp_path = tmp.name

    try:
        model = get_model(model_name)
        segments_iter, info = model.transcribe(
            tmp_path,
            word_timestamps=want_words,
            beam_size=beam_size,
            vad_filter=True,  # skip long silences -> cleaner segment boundaries & faster processing
            vad_parameters=dict(min_silence_duration_ms=500),
        )


        segments = []
        full_text_parts = []
        for seg in segments_iter:
            words = []
            if want_words and seg.words:
                for w in seg.words:
                    words.append(
                        {"word": w.word, "start": w.start, "end": w.end}
                    )
            text = (seg.text or "").strip()
            full_text_parts.append(text)
            segments.append(
                {"start": seg.start, "end": seg.end, "text": text, "words": words}
            )

        return jsonify(
            language=info.language,
            text=" ".join(full_text_parts).strip(),
            segments=segments,
        )
    finally:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass


if __name__ == "__main__":
    port = int(os.environ.get("WHISPER_PORT", "9000"))
    # Threaded so a slow transcription doesn't block the health check.
    app.run(host="127.0.0.1", port=port, threaded=True)
