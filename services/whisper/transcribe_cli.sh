#!/usr/bin/env bash
# ClipForge-AI Whisper client.
# Transcribe a local audio/video file via the running whisper service (port 9000).
#
# Usage:
#   ./transcribe_cli.sh <file> [--model small] [--no-words] [--json]
#
# Examples:
#   ./transcribe_cli.sh meeting.mp3
#   ./transcribe_cli.sh clip.mp4 --model medium --json > out.json
#   ./transcribe_cli.sh talk.wav --no-words
#
set -euo pipefail

WHISPER_URL="${WHISPER_URL:-http://127.0.0.1:9000}"
MODEL=""
WANT_WORDS="true"
AS_JSON="false"
FILE=""

while [[ $# -gt 0 ]]; do
  case "$1" in
    --model) MODEL="$2"; shift 2;;
    --no-words) WANT_WORDS="false"; shift;;
    --json) AS_JSON="true"; shift;;
    -h|--help) sed -n '3,16p' "$0"; exit 0;;
    *) FILE="$1"; shift;;
  esac
done

if [[ -z "$FILE" || ! -f "$FILE" ]]; then
  echo "ERROR: file not found: '$FILE'" >&2
  echo "Usage: $0 <file> [--model small] [--no-words] [--json]" >&2
  exit 1
fi

# Build form args
FORM=(-F "file=@${FILE}")
[[ -n "$MODEL" ]] && FORM+=(-F "model=${MODEL}")
FORM+=(-F "word_timestamps=${WANT_WORDS}")

# Verify service is up
if ! curl -s --max-time 3 "${WHISPER_URL}/health" >/dev/null 2>&1; then
  echo "ERROR: whisper service not reachable at ${WHISPER_URL}/health" >&2
  echo "       Start it with: cd services/whisper && .venv/bin/python app.py" >&2
  exit 2
fi

RESP=$(curl -s --max-time 600 -X POST "${WHISPER_URL}/transcribe" "${FORM[@]}")

if [[ "$AS_JSON" == "true" ]]; then
  echo "$RESP"
  exit 0
fi

# Human-readable: print detected language + full text
echo "$RESP" | python3 -c '
import sys, json
try:
    d = json.load(sys.stdin)
except Exception as e:
    print("ERROR: bad response from whisper service:", file=sys.stderr)
    print(sys.stdin.read() if False else "", file=sys.stderr)
    sys.exit(3)
if "error" in d:
    print("ERROR:", d["error"], file=sys.stderr)
    sys.exit(4)
lang = d.get("language", "?")
print(f"[language: {lang}]")
print()
print(d.get("text", ""))
'