"""
Face-tracking service for Auto-Clip (Stage 4, spec section 5.4).

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

try:
    import mediapipe as mp
except ImportError:
    mp = None

from flask import Flask, jsonify, request

app = Flask(__name__)

mp_face = None
frontal_detector = None
profile_detector = None

frontal_xml = os.path.join(os.path.dirname(__file__), "haarcascade_frontalface_default.xml")
if os.path.exists(frontal_xml):
    d = cv2.CascadeClassifier(frontal_xml)
    if not d.empty():
        frontal_detector = d

profile_xml = os.path.join(os.path.dirname(__file__), "haarcascade_profileface.xml")
if os.path.exists(profile_xml):
    p = cv2.CascadeClassifier(profile_xml)
    if not p.empty():
        profile_detector = p

# Sample at most this many points per clip — enough for a smooth pan without
# processing every frame.
SAMPLE_HZ = float(os.environ.get("FACE_SAMPLE_HZ", "4"))


@app.get("/health")
def health():
    active_detector = "mediapipe" if mp_face else ("opencv_frontal_profile" if (frontal_detector or profile_detector) else "none")
    return jsonify(status="ok", sample_hz=SAMPLE_HZ, detector=active_detector, has_detector=(active_detector != "none"))


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


def _detect_faces(frame_gray):
    faces = []
    if frontal_detector:
        f = frontal_detector.detectMultiScale(frame_gray, scaleFactor=1.1, minNeighbors=4, minSize=(30, 30))
        for rect in f:
            faces.append(rect)

    if profile_detector:
        # Left-facing profiles
        p = profile_detector.detectMultiScale(frame_gray, scaleFactor=1.1, minNeighbors=4, minSize=(30, 30))
        for rect in p:
            faces.append(rect)

        # Right-facing profiles (via horizontal flip)
        flipped = cv2.flip(frame_gray, 1)
        p_flip = profile_detector.detectMultiScale(flipped, scaleFactor=1.1, minNeighbors=4, minSize=(30, 30))
        img_w = frame_gray.shape[1]
        for (x, y, w, h) in p_flip:
            unflipped_x = img_w - (x + w)
            faces.append((unflipped_x, y, w, h))

    return faces


def _sample_centers(path: str, start_ms: int, end_ms: int) -> list[dict]:
    if not mp_face and not frontal_detector and not profile_detector:
        return []

    cap = cv2.VideoCapture(path)
    if not cap.isOpened():
        return []

    fps = cap.get(cv2.CAP_PROP_FPS) or 30.0
    frame_delay = 1000.0 / fps
    step_ms = max(1, int(1000 / SAMPLE_HZ))

    cap.set(cv2.CAP_PROP_POS_MSEC, start_ms)

    centers: list[dict] = []
    next_target_ms = start_ms
    last_cx = None

    if mp_face:
        with mp_face.FaceDetection(model_selection=1, min_detection_confidence=0.5) as fd:
            while True:
                ok, frame = cap.read()
                if not ok:
                    break

                current_ms = cap.get(cv2.CAP_PROP_POS_MSEC)
                if current_ms > end_ms:
                    break

                if current_ms >= next_target_ms:
                    h, w = frame.shape[:2]
                    if w > 640:
                        target_h = max(1, int(h * (640.0 / w)))
                        proc_frame = cv2.resize(frame, (640, target_h), interpolation=cv2.INTER_NEAREST)
                    else:
                        proc_frame = frame

                    rgb = cv2.cvtColor(proc_frame, cv2.COLOR_BGR2RGB)
                    result = fd.process(rgb)

                    if result.detections:
                        best = max(
                            result.detections,
                            key=lambda d: d.location_data.relative_bounding_box.width,
                        )
                        box = best.location_data.relative_bounding_box
                        cx = box.xmin + box.width / 2.0
                        centers.append({"t_ms": int(current_ms), "cx": max(0.0, min(1.0, float(cx)))})

                    next_target_ms += step_ms
                    while current_ms < next_target_ms - frame_delay:
                        if not cap.grab():
                            break
                        current_ms = cap.get(cv2.CAP_PROP_POS_MSEC)
    else:
        while True:
            ok, frame = cap.read()
            if not ok:
                break

            current_ms = cap.get(cv2.CAP_PROP_POS_MSEC)
            if current_ms > end_ms:
                break

            if current_ms >= next_target_ms:
                h, w = frame.shape[:2]
                if w > 640:
                    target_h = max(1, int(h * (640.0 / w)))
                    proc_frame = cv2.resize(frame, (640, target_h), interpolation=cv2.INTER_NEAREST)
                else:
                    proc_frame = frame

                gray = cv2.cvtColor(proc_frame, cv2.COLOR_BGR2GRAY)
                detected = _detect_faces(gray)

                if len(detected) > 0:
                    # If previously tracked a face, prefer face closest to last_cx; else pick largest face
                    if last_cx is not None:
                        chosen = min(detected, key=lambda f: abs(((f[0] + f[2]/2.0)/proc_frame.shape[1]) - last_cx))
                    else:
                        chosen = max(detected, key=lambda f: f[2] * f[3])

                    fx, fy, fw, fh = chosen
                    cx = (fx + fw / 2.0) / proc_frame.shape[1]
                    last_cx = cx
                    centers.append({"t_ms": int(current_ms), "cx": max(0.0, min(1.0, float(cx)))})

                next_target_ms += step_ms
                while current_ms < next_target_ms - frame_delay:
                    if not cap.grab():
                        break
                    current_ms = cap.get(cv2.CAP_PROP_POS_MSEC)

    cap.release()
    return centers


if __name__ == "__main__":
    port = int(os.environ.get("FACE_PORT", "9100"))
    app.run(host="127.0.0.1", port=port, threaded=True)
