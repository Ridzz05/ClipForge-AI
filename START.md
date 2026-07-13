# START — Menjalankan Auto-Clip AI dari Nol

Panduan ini menjelaskan **di mana** setiap komponen dipasang/dijalankan dan
**perintah persisnya**, untuk Windows 11 (mesin dev ini). Urutannya: pasang
binary → tarik model → jalankan service → jalankan Laravel → uji satu video.

Ringkasan arsitektur: Laravel adalah orkestrator. Kerja berat dijalankan
sebagai queue job yang memanggil binary (**ffmpeg/ffprobe**) dan tiga service
HTTP lokal (**whisper**, **ollama**, **mediapipe**). Semuanya berjalan di
`127.0.0.1` — tidak ada yang menghadap internet.

```
                        ┌────────────── Laravel (php artisan serve, :8000)
  upload video ───────► │  queue worker (php artisan queue:work)
                        └──┬───────┬───────────┬───────────┬──────────
                           │       │           │           │
                    ffprobe/ffmpeg │           │           │
                    (binary, PATH) │           │           │
                                   ▼           ▼           ▼
                             whisper:9000  ollama:11434  mediapipe:9100
```

---

## 1. ffmpeg & ffprobe — di mana "dijalankan"?

**Tidak dijalankan sebagai service.** Keduanya adalah binary command-line yang
**dipanggil oleh Laravel per job** (Stage 1 validasi durasi, Stage 4 crop +
caption, Stage 5 watermark). Kamu hanya perlu memasangnya sekali sehingga ada
di **PATH** — Laravel memanggilnya lewat `Symfony\Process` dengan argument
array (aman dari command injection).

### Pasang di Windows

Pilih salah satu:

**Opsi A — winget (paling gampang):**
```bash
winget install Gyan.FFmpeg
```

**Opsi B — Chocolatey:**
```bash
choco install ffmpeg-full
```

**Opsi C — manual:** unduh build dari <https://www.gyan.dev/ffmpeg/builds/>
(`ffmpeg-release-full.7z`), ekstrak, lalu tambahkan folder `bin/` ke PATH
sistem (Settings → Environment Variables → Path → New).

### Verifikasi (buka terminal BARU setelah instalasi)
```bash
ffmpeg -version
ffprobe -version
```
Keduanya harus mencetak versi. Kalau "command not found", PATH belum ke-refresh
— tutup lalu buka lagi terminalnya.

### Kalau tidak mau menaruhnya di PATH
Set path absolut di `.env`:
```
AUTOCLIP_FFMPEG_PATH=C:/ffmpeg/bin/ffmpeg.exe
AUTOCLIP_FFPROBE_PATH=C:/ffmpeg/bin/ffprobe.exe
```
(Config membaca dua key ini — lihat `config/autoclip.php`.)

---

## 2. Ollama + model — di mana model diunduh?

**Ollama adalah service** (default sudah jalan di `http://127.0.0.1:11434`).
Model **diunduh oleh Ollama sendiri** dengan perintah `ollama pull`, dan
disimpan di cache Ollama (Windows: `C:\Users\<kamu>\.ollama\models`). Laravel
tidak menyentuh file model — ia hanya memanggil HTTP `/api/generate`.

### Pasang Ollama (kalau belum)
```bash
winget install Ollama.Ollama
```
Atau unduh installer dari <https://ollama.com/download>.

### Tarik model scoring (Stage 3)
```bash
# Model default yang dipakai config (qwen2.5:7b, ~4.7 GB unduhan)
ollama pull qwen2.5:7b
```

Kalau RAM terbatas, pakai model lebih ringan lalu arahkan config ke sana:
```bash
ollama pull llama3.2:3b          # ~2 GB
# lalu di .env:
# AUTOCLIP_OLLAMA_MODEL=llama3.2:3b
```

### Verifikasi
```bash
ollama list                      # model harus muncul di daftar
curl http://127.0.0.1:11434/api/tags
```
Kalau `curl` gagal, service Ollama belum jalan — jalankan `ollama serve`
(installer Windows biasanya sudah menjalankannya otomatis di background).

---

## 3. Service Whisper (transkripsi, Stage 2)

Service Python sendiri di `services/whisper`. **Butuh Python 3.11 + ffmpeg**
(whisper decode audio via ffmpeg, jadi pasang ffmpeg di langkah 1 dulu).

```bash
cd services/whisper
python -m venv .venv
.venv\Scripts\activate           # Windows
pip install -r requirements.txt

python app.py                    # jalan di http://127.0.0.1:9000
```
Unduhan model whisper (`small`, ~500 MB) terjadi **otomatis saat request
pertama**, disimpan di cache HuggingFace (`C:\Users\<kamu>\.cache\huggingface`).

Verifikasi: `curl http://127.0.0.1:9000/health`
Detail lengkap: `services/whisper/README.md`.

---

## 4. Service MediaPipe (face tracking, Stage 4 — OPSIONAL)

Service Python di `services/reframe`. **Opsional**: kalau mati, Laravel otomatis
fallback ke center-crop (render tetap jalan, cuma tidak tracking wajah).

```bash
cd services/reframe
python -m venv .venv
.venv\Scripts\activate
pip install -r requirements.txt

python app.py                    # jalan di http://127.0.0.1:9100
```
Detail: `services/reframe/README.md`.

---

## 5. Laravel (orkestrator)

Sudah ter-setup di repo ini. Yang perlu dijalankan:

```bash
# (sekali) pastikan dependency & DB siap
composer install
php artisan migrate

# Terminal 1 — web/API
php artisan serve                # http://127.0.0.1:8000

# Terminal 2 — queue worker (WAJIB, ini yang menjalankan semua stage)
php artisan queue:work --tries=3
```

> Tanpa `queue:work`, upload akan tersimpan tapi tidak pernah diproses —
> job hanya menumpuk di antrian.

---

## 6. Uji satu video (end-to-end)

Dengan ffmpeg terpasang + whisper/ollama jalan + `queue:work` aktif:

```bash
# 1. Ingest — balas {id, status, duration_seconds}
curl -F "video=@sample.mp4" http://127.0.0.1:8000/api/videos

# 2. Tunggu worker jalan (lihat log queue:work). Pipeline otomatis:
#    transcribe -> score -> status video jadi "reviewing"

# 3. Lihat kandidat klip yang dihasilkan (via tinker atau query DB)
php artisan tinker --execute="App\Models\ClipCandidate::all(['id','hook_score','start_ms','end_ms'])"

# 4. Setujui satu kandidat -> memicu render (Stage 4+5)
curl -X POST http://127.0.0.1:8000/api/candidates/1/approve

# 5. Setelah render selesai, unduh hasilnya
curl -OJ http://127.0.0.1:8000/api/exports/1/download
```

Watermark opsional: set `AUTOCLIP_WATERMARK_PATH` di `.env` ke path PNG
absolut. Kalau kosong, klip diekspor tanpa watermark.

---

## Checklist "sudah siap render nyata?"

| Komponen | Cek | Wajib? |
|---|---|---|
| ffmpeg/ffprobe | `ffmpeg -version` jalan | ✅ Ya (Stage 1,4,5) |
| Ollama + model | `ollama list` memuat `qwen2.5:7b` | ✅ Ya (Stage 3) |
| Whisper service | `curl :9000/health` | ✅ Ya (Stage 2) |
| MediaPipe service | `curl :9100/health` | ⬜ Opsional (fallback center-crop) |
| `php artisan serve` | `curl :8000/up` | ✅ Ya |
| `php artisan queue:work` | jalan di terminal terpisah | ✅ Ya |

Semua endpoint di `127.0.0.1` — ubah port lewat env terkait di `.env`
(`AUTOCLIP_WHISPER_ENDPOINT`, `AUTOCLIP_OLLAMA_ENDPOINT`,
`AUTOCLIP_FACE_ENDPOINT`).

---

## Catatan test vs runtime

Test suite (`php artisan test`, 83 hijau) **memfake** semua binary & service di
atas — jadi test jalan tanpa memasang apa pun. Langkah-langkah di file ini
hanya diperlukan untuk **memproses video sungguhan**, bukan untuk menjalankan
test.
