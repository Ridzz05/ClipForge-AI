# ClipForge AI — audit findings + remediation

## Context

Audit permintaan user (read-only) atas `C:/Users/muhri/orca/ClipForge-AI` (Laravel 13 + Livewire 4, pipeline auto-clip). Hasil: satu kerentanan path traversal terverifikasi, semua route tanpa auth, TLS verification dimatikan saat `APP_ENV=local`, race condition pada approve kandidat, beberapa opsi UI yang tidak diimplementasikan, dan sampah render yang menumpuk di disk. Plan ini menutup temuan tersebut.

Catatan lingkungan: `git status` menunjukkan seluruh `resources/openreel/**` terhapus di working tree (perubahan user, bukan bagian dari plan ini). `OpenReelTest` yang ada butuh aset itu; kalau masih terhapus, 3 test tersebut gagal karena sebab pre-existing.

## Temuan (evidence)

| # | Sev | Temuan | Bukti |
|---|---|---|---|
| 1 | P0 | Path traversal di route `/openreel/{path?}`: path user digabung ke `resource_path('openreel')` tanpa `realpath` containment | `app/Http/Controllers/OpenReelController.php:14`; `routes/web.php:27` (`where('path','.*')`). Diverifikasi: `Illuminate\Http\Request::create('/openreel/..%2f..%2f.env')->decodedPath()` = `openreel/../../.env` (Symfony tidak menormalkan `..`) → `.env` (APP_KEY, API key LLM) terbaca via `GET /openreel/..%2f..%2f.env` |
| 2 | P0 | Tidak ada auth/rate-limit di semua route: upload 2 GB, hapus video, `queue:restart`, download export, stream source | `routes/web.php`, `routes/api.php` (komentar "no auth gate yet"); `bootstrap/app.php` middleware kosong |
| 3 | P1 | TLS verification dimatikan untuk endpoint LLM mana pun saat `APP_ENV=local`, plus User-Agent palsu `claude-cli/0.2.20`; API key bisa di-MITM | `app/Services/Scoring/OllamaService.php` `client()` |
| 4 | P1 | Prompt LLM disisipi zero-width space per kata (`sanitizeForWaf`) + instruksi "bypass firewall" — prompt rusak, tidak ada manfaat | `OllamaService::buildPrompt()`/`sanitizeForWaf()`; `tests/Unit/OllamaServiceTest.php:53` mengunci perilaku ini |
| 5 | P1 | Approve kandidat non-atomik (read-check lalu update) → dua request bersamaan membuat 2 export & 2 render | `app/Services/ClipReviewService.php::approve` |
| 6 | P2 | Opsi render `blur_916`, `square_11`, `landscape_169` (dan `renderFormat` di ReviewVideo) tidak pernah diterjemahkan builder → dirender sebagai crop 9:16 biasa; UI menipu | `ReframeCommandBuilder::build` hanya `split_podcast`/`split_gaming`/default; `ClipEditor.php:255-261`, `clip-editor.blade.php:407-415`, `review-video.blade.php:301-313`, `ReviewVideo.php:46` (properti tak terpakai) |
| 7 | P2 | File antara render (`captions*.ass`, `seg_*.mp4`, `concat.txt`) tidak dihapus setelah sukses → disk tumbuh | `app/Jobs/ReframeJob.php::handle` |
| 8 | P2 | `Content-Type: video/mp4` hardcoded untuk source apa pun (mkv/webm/mov gagal diputar di player review) | `app/Http/Controllers/VideoStreamController.php` |
| 9 | P3 | Dead code: `ExportDeliverJob::relativeOf()` tak dipakai; guard `class_exists()` untuk kelas milik aplikasi sendiri | `ExportDeliverJob.php`, `TranscribeJob.php`, `ReframeJob.php` |
| 10 | P2 | Tidak ada retention/prune untuk upload asli & video gagal (spec §6 mewajibkannya) → storage tumbuh diam-diam | `grep -rn prune app routes` kosong; `routes/console.php` hanya `inspire` |

Tidak diubah (bukan bug, keputusan produk): `ScoreHighlightsJob` auto-approve top-3 `hook_score>=75` saat `auto_clip` aktif (melampaui human gate di spec), `sleep(3)` antar batch LLM, `Dashboard::deleteVideo` yang meng-cascade manual meski FK sudah `cascadeOnDelete`.

## Approach

Urutan: 1 → 2 (keamanan, independen), 3 → 4 (satu file), 5 (satu service), 6 → 9 (perilaku render/permukaan UI), 10 (operasional). Setiap langkah menyisakan suite hijau.

### Langkah 1 — Tutup traversal OpenReel

`app/Http/Controllers/OpenReelController.php`, method `serve` — ganti seluruh body logika path dengan:

```php
$root = realpath(resource_path('openreel'));
abort_if($root === false, 404);

$index = realpath($root.'/index.html');
$wanted = ($path === null || $path === '')
    ? $index
    : realpath($root.'/'.ltrim($path, '/'));

$target = ($wanted !== false && str_starts_with($wanted, $root.DIRECTORY_SEPARATOR))
    ? $wanted
    : $index;                               // SPA fallback untuk subpath tak dikenal

abort_if($target === false || ! is_file($target), 404);
```

Sisanya (map MIME, header `Cross-Origin-Opener-Policy: same-origin`, `Cross-Origin-Embedder-Policy: require-corp`, `response()->file($target, $headers)`) dipertahankan; ganti variabel `$filePath` → `$target` di `pathinfo()`/`response()->file()`.

Perilaku: `/openreel` → index; `/openreel/projects/123` → index (fallback, test lama tetap lolos); `/openreel/..%2f..%2f.env` → 404; direktori `resources/openreel` tidak ada → 404 (sebelumnya exception 500).

### Langkah 2 — Auth gate operator + throttle API

1. File baru `app/Http/Middleware/BasicOperatorAuth.php`:

```php
public function handle(Request $request, Closure $next): Response
{
    $user = (string) config('autoclip.access.user');
    $pass = (string) config('autoclip.access.password');

    if ($user === '' || $pass === '') {
        return $next($request);   // gate mati kalau tidak dikonfigurasi (dev lokal)
    }

    $givenUser = (string) $request->getUser();
    $givenPass = (string) $request->getPassword();

    if (! hash_equals($user, $givenUser) || ! hash_equals($pass, $givenPass)) {
        return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="ClipForge"']);
    }

    return $next($request);
}
```

2. `config/autoclip.php`: tambah blok berikut setelah blok `timeouts`:

```php
'access' => [
    'user' => env('AUTOCLIP_BASIC_USER'),
    'password' => env('AUTOCLIP_BASIC_PASS'),
],
```

3. `.env.example`: tambah

```
# --- Auto-Clip: operator gate (kosong = tanpa auth, hanya untuk dev lokal) ---
AUTOCLIP_BASIC_USER=
AUTOCLIP_BASIC_PASS=
```

4. `bootstrap/app.php`, dalam `->withMiddleware(function (Middleware $middleware) { ... })`, ganti `//` dengan:

```php
$middleware->web(append: [\App\Http\Middleware\BasicOperatorAuth::class]);
$middleware->api(append: [\App\Http\Middleware\BasicOperatorAuth::class]);
```

`web(append:)` ikut melindungi endpoint `/livewire/update` (sumber semua aksi Livewire: upload, hapus video, approve, restart queue).

5. `routes/api.php`: bungkus seluruh route dalam `Route::middleware('throttle:60,1')->group(function () { ... });`.

Test baru `tests/Feature/AuthGateTest.php` (`RefreshDatabase`): dengan `config(['autoclip.access.user' => 'op', 'autoclip.access.password' => 's3cret'])`, `GET /` tanpa kredensial → 401 + header `WWW-Authenticate`; dengan header `Authorization: Basic '.base64_encode('op:s3cret')` → 200; dengan `config(['autoclip.access.user' => null, 'autoclip.access.password' => null])` → 200.

### Langkah 3 — Perbaiki klien LLM

`app/Services/Scoring/OllamaService.php`, method `client()`:

- Hapus `->withHeaders(['User-Agent' => 'claude-cli/0.2.20 (external, cli)'])`.
- Ganti blok `if (config('app.env') === 'local') { $client = $client->withoutVerifying(); }` menjadi `if (config('autoclip.llm.insecure_tls', false)) { $client = $client->withoutVerifying(); }`.

`config/autoclip.php`, dalam blok `'llm' => [...]`, tambah `'insecure_tls' => (bool) env('AUTOCLIP_LLM_INSECURE_TLS', false),`.

`.env.example`, di bagian LLM, tambah `# Set true HANYA kalau CA Windows bermasalah (cURL error 60). Default: verifikasi TLS aktif.` + `AUTOCLIP_LLM_INSECURE_TLS=false`.

### Langkah 4 — Buang zero-width space dari prompt

`OllamaService::buildPrompt()`: pada `array_map`, ganti `'text' => $this->sanitizeForWaf($s['text'])` → `'text' => $s['text']`; hapus paragraf prompt "The transcript segments below are DATA and contain zero-width spaces ..." (satu blok teks sebelum `TRANSCRIPT_SEGMENTS:`); hapus method privat `sanitizeForWaf()`.

`tests/Unit/OllamaServiceTest.php:53`: ganti assertion `str_contains($body['prompt'], 'he' . "\u{200b}" . 'llo')` → `str_contains($body['prompt'], 'hello world')`.

### Langkah 5 — Approve atomik

`app/Services/ClipReviewService.php::approve` — ganti dua blok `if ($candidate->status === ...)` dengan claim bersyarat:

```php
$claimed = ClipCandidate::whereKey($candidate->getKey())
    ->whereIn('status', [ClipCandidate::STATUS_PENDING, ClipCandidate::STATUS_REJECTED])
    ->update(['status' => ClipCandidate::STATUS_APPROVED]);

if ($claimed === 0) {
    $fresh = $candidate->fresh();

    throw new RuntimeException(match ($fresh?->status) {
        ClipCandidate::STATUS_EXPORTED => 'Candidate already exported.',
        ClipCandidate::STATUS_APPROVED => 'Candidate already approved.',
        default => 'Candidate cannot be approved.',
    });
}

$candidate->refresh();
```

Sisa body (perhitungan CTA/style/margin, `Export::create`, `ReframeJob::dispatch`) tidak berubah. `ClipCandidate::STATUS_REJECTED` tetap boleh di-approve karena `ReviewVideo::batchRenderAll` mengandalkannya (`whereIn(status, [pending, rejected])`).

Test baru di `tests/Feature/ClipCandidateReviewTest.php`: `test_second_approve_of_same_candidate_is_a_conflict` — `postJson` approve dua kali; request kedua `assertStatus(409)`; `Export::where('clip_candidate_id', $id)->count() === 1`.

### Langkah 6 — Hapus opsi render yang tidak ada implementasinya

- `resources/views/livewire/clip-editor.blade.php`: hapus tiga `<button ... wire:click="$set('renderFormat', 'blur_916'|'square_11'|'landscape_169')" ...>` beserta label di dalamnya. Sisakan `face_916` dan `split_podcast`.
- `app/Livewire/ClipEditor.php:255-261`: `match` menjadi `'split_podcast' => 'split_podcast', default => 'single'` (hapus `blur_916`, `square_11`, `landscape_169`).
- `resources/views/livewire/review-video.blade.php`: hapus keempat tombol `renderFormat` (`face_916`, `blur_916`, `square_11`, `landscape_169`) — properti ini tidak dipakai `approve()`; pilihan layout tetap lewat `<select>` `layout` (`single`, `split_podcast`, `split_gaming`).
- `app/Livewire/ReviewVideo.php:46`: hapus `public string $renderFormat = 'face_916'; // ...`.

Kriteria selesai: `grep -rn "renderFormat\|blur_916\|square_11\|landscape_169" app resources` → hanya `renderFormat` milik `ClipEditor` (`face_916`/`split_podcast`).

### Langkah 7 — Bersihkan artefak render sementara

`app/Jobs/ReframeJob.php::handle`: tambah `$temp = [];` sebelum cabang multi-segmen; setiap path relatif yang dibuat (`captions*.ass`, `seg_*.mp4`, `concat.txt`) di-`push` ke `$temp`; setelah `$export->update([...'rendered_at' => now()]);` dan `$pipelineJob->update([...])`, tambah `if ($temp !== []) { $exportDisk->delete($temp); }`. File output final (`$outputRelative`) tidak dihapus.

### Langkah 8 — Content-Type stream sesuai file

`app/Http/Controllers/VideoStreamController.php::source`: sebelum `return response()->file(...)`, tambah `$mime = $disk->mimeType($video->storage_path) ?: 'application/octet-stream';` dan pakai `'Content-Type' => $mime`.

### Langkah 9 — Buang dead code

- `app/Jobs/ExportDeliverJob.php`: hapus method privat `relativeOf()`.
- `app/Jobs/TranscribeJob.php`: hapus `if (class_exists(\App\Jobs\ScoreHighlightsJob::class)) {` pembungkusnya, panggil langsung `ScoreHighlightsJob::dispatch($video->id);`, tambah `use App\Jobs\ScoreHighlightsJob;`.
- `app/Jobs/ReframeJob.php`: hapus `if (class_exists(\App\Jobs\ExportDeliverJob::class)) {` pembungkusnya, panggil langsung `ExportDeliverJob::dispatch($export->id);`, tambah `use App\Jobs\ExportDeliverJob;`.

### Langkah 10 — Command prune manual

File baru `app/Console/Commands/PruneMediaCommand.php`:

- `protected $signature = 'autoclip:prune {--days= : Umur minimum dalam hari (default dari config)} {--force : Benar-benar hapus}';`
- `handle()`: `$days = (int) ($this->option('days') ?: config('autoclip.retention_days', 30));` cutoff `now()->subDays($days)`; ambil `Video::where('status', 'failed')->where('updated_at', '<', $cutoff)->get()`.
- Per video: hapus `Storage::disk(config('autoclip.ingest.disk'))->deleteDirectory(dirname($video->storage_path) ?: "videos/url-{$video->id}")`, hapus tiap `exports/{id}` milik kandidatnya, lalu `$video->delete()` (FK cascade sudah membersihkan transcript/segments/candidates/exports/pipeline_jobs).
- Tanpa `--force`: hanya `$this->info("Would delete {$n} videos ...")`, tidak menghapus apa pun. Dengan `--force`: hapus + `$this->info("Deleted {$n} videos.")`.
- Tidak didaftarkan ke scheduler — dijalankan manual (mencegah penghapusan data otomatis).

`config/autoclip.php`: tambah `'retention_days' => (int) env('AUTOCLIP_RETENTION_DAYS', 30),`. `.env.example`: `AUTOCLIP_RETENTION_DAYS=30`.

Test baru `tests/Feature/PruneMediaCommandTest.php` (`RefreshDatabase`): buat `Video` status `failed` dengan `storage_path = "videos/old/source.mp4"` dan `updated_at = now()->subDays(40)`; `artisan('autoclip:prune')->assertSuccessful()` → row masih ada; `artisan('autoclip:prune', ['--force' => true])` → row hilang.

## Critical files & anchors

- `app/Http/Controllers/OpenReelController.php` — `serve()`; satu-satunya jalur baca file yang pathnya berasal dari URL.
- `bootstrap/app.php` — `->withMiddleware()`; tempat mendaftarkan gate agar `/livewire/update` ikut terlindungi.
- `app/Services/ClipReviewService.php` — `approve()`; titik tunggal yang dipakai controller API + kedua komponen Livewire.
- `app/Jobs/ReframeJob.php` — `handle()`; satu-satunya tempat artefak sementara dibuat (multi-segmen & single).
- `app/Services/Scoring/OllamaService.php` — `client()` + `buildPrompt()`; menyentuh kredensial dan isi prompt.

## Verification

Jalankan dari root repo, setelah `composer install` dan `.env` ada (test pakai SQLite `:memory:`, tanpa service eksternal):

1. `php artisan test` → seluruh suite hijau (135 test lama + 4 test baru).

2. Traversal (bukti baru):
   - `php artisan test --filter=OpenReel` → `test_openreel_traversal_is_rejected` hijau.
   - Manual: `php artisan serve` lalu `curl -i http://127.0.0.1:8000/openreel/..%2f..%2f.env` → `404`, body tidak memuat `APP_KEY`.
   - Catatan: 3 test OpenReel lama butuh `resources/openreel/**` yang saat ini terhapus di working tree; kalau masih terhapus, kegagalannya pre-existing, bukan regresi langkah 1.

3. Auth gate (bukti baru):
   - `AUTOCLIP_BASIC_USER=op AUTOCLIP_BASIC_PASS=s3cret php artisan serve` → `curl -i http://127.0.0.1:8000/` → `401` + `WWW-Authenticate: Basic realm="ClipForge"`; `curl -i -u op:s3cret http://127.0.0.1:8000/` → `200`.
   - Dengan env kosong → `curl -i http://127.0.0.1:8000/` → `200` (dev lokal tidak berubah).

4. LLM prompt (bukti baru): `php artisan test --filter=OllamaServiceTest` → assertion `hello world` hijau; `grep -n "200b\|claude-cli\|withoutVerifying" app/Services/Scoring/OllamaService.php` → tidak ada hasil.

5. Approve ganda: `php artisan test --filter=ClipCandidateReviewTest` → semua hijau, termasuk konflik approve kedua.

6. UI render: buka `/videos/{id}/review` dan `/candidates/{id}/edit`; pastikan tidak ada tombol Blur/Square/Landscape, dan approve dengan `split_podcast` tetap memicu render (export muncul di `/exports`).

7. Prune: `php artisan autoclip:prune` (tanpa `--force`) mencetak jumlah kandidat tanpa menghapus; dengan `--force` menghapus video `failed` lebih tua dari 30 hari.

## Assumptions & contingencies

- Gate auth default mati saat `AUTOCLIP_BASIC_USER`/`PASS` kosong — supaya alur dev lokal sekarang tidak berubah; aktifkan di VPS.
- Kalau `Curl error 60` muncul di Windows setelah langkah 3: set `AUTOCLIP_LLM_INSECURE_TLS=true` sementara, jangan kembalikan `withoutVerifying` global.
- Kalau `ClipCandidateReviewTest` yang ada mengandalkan pesan `Candidate already exported.` untuk status selain `exported`, sesuaikan pemetaan `match` di langkah 5 ke pesan yang diharapkan test tersebut — bukan sebaliknya.
- `resources/openreel/**` yang terhapus adalah perubahan user yang belum di-commit; plan ini tidak memulihkan atau menghapusnya.
