"""
MediaPipe face-tracking service for Auto-Clip (Stage 4, spec section 5.4).

Runs as its own process (mirrors whisper/ollama). Given a media file and a clip
range, it samples the horizontal centre of the primary face over time. The
Laravel FaceTrackingService calls it; the ReframePlanner turns the returned
centres into a smoothed 9:16 pan path. If this service is down, Laravel falls
back to a static centre crop, so this is a quality enhancer, not a hard dep.

    POST /track  (multipart: file=<media>, start_ms=<int>, end_ms=<int>)
    ->  {"centers": [{"t_ms": <int>, "cx": <float 0..1>}, ...]}

cx is the face centre X normalised to 0..1 across the frame width.
"""
import os
import tempfile

import cv2
import mediapipe as mp
from flask import Flask, jsonify, request

app = Flask(__name__)

mp_face = mp.solutions.face_detection

# Sample at most this many points per clip — enough for a smooth pan without
# processing every frame.
SAMPLE_HZ = float(os.environ.get("FACE_SAMPLE_HZ", "4"))


@app.get("/health")
def health():
    return jsonify(status="ok", sample_hz=SAMPLE_HZ)


@app.post("/track")
def track():
    if "file" not in request.files:
        return jsonify(error="missing 'file'"), 400

    start_ms = int(request.form.get("start_ms", 0))
    end_ms = int(request.form.get("end_ms", 0))

    upload = request.files["file"]
    suffix = os.path.splitext(upload.filename or "")[1] or ".mp4"
    with tempfile.NamedTemporaryFile(suffix=suffix, delete=False) as tmp:
        upload.save(tmp.name)
        tmp_path = tmp.name

    try:
        centers = _sample_centers(tmp_path, start_ms, end_ms)
        return jsonify(centers=centers)
    finally:
        try:
            os.unlink(tmp_path)
        except OSError:
            pass


def _sample_centers(path: str, start_ms: int, end_ms: int) -> list[dict]:
    cap = cv2.VideoCapture(path)
    if not cap.isOpened():
        return []

    fps = cap.get(cv2.CAP_PROP_FPS) or 30.0
    step_ms = max(1, int(1000 / SAMPLE_HZ))

    centers: list[dict] = []
    with mp_face.FaceDetection(model_selection=1, min_detection_confidence=0.5) as fd:
        t = start_ms
        while t <= end_ms:
            cap.set(cv2.CAP_PROP_POS_MSEC, t)
            ok, frame = cap.read()
            if not ok:
                break

            rgb = cv2.cvtColor(frame, cv2.COLOR_BGR2RGB)
            result = fd.process(rgb)

            if result.detections:
                # Largest detection = primary speaker.
                best = max(
                    result.detections,
                    key=lambda d: d.location_data.relative_bounding_box.width,
                )
                box = best.location_data.relative_bounding_box
                cx = box.xmin + box.width / 2.0
                centers.append({"t_ms": t, "cx": max(0.0, min(1.0, float(cx)))})

            t += step_ms

    cap.release()
    return centers


if __name__ == "__main__":
    port = int(os.environ.get("FACE_PORT", "9100"))
    app.run(host="127.0.0.1", port=port, threaded=True)
