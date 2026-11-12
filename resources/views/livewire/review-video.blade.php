@php
    // Preset CTA labels carry emoji from config; strip them for display only (the stored text is untouched).
    $revPlain = static fn (string $s): string => trim((string) preg_replace('/[\x{1F000}-\x{1FAFF}\x{2600}-\x{27BF}\x{2B00}-\x{2BFF}\x{2190}-\x{21FF}\x{FE0F}]/u', '', $s));
    $headlineStyleClass = match ($headlineStyle) {
        'highlight' => 'rev-hl-highlight',
        'sticker' => 'rev-hl-sticker',
        default => 'rev-hl-marker',
    };
@endphp

<div @if($poll) wire:poll.4s @endif class="rev">

    <style>
        .rev { display: flex; flex-direction: column; gap: var(--s-6); }
        .rev-layout { display: grid; gap: var(--s-6); align-items: start; }
        @media (min-width: 901px) { .rev-layout { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }
        .rev-col { display: flex; flex-direction: column; gap: var(--s-5); min-width: 0; }
        .rev-panel { display: flex; flex-direction: column; gap: var(--s-4); min-width: 0; }
        .rev-video { display: block; width: 100%; aspect-ratio: 16 / 9; border: 1px solid var(--border-ctl); border-radius: var(--r); background: var(--paper-2); }
        .rev-label { font-size: 0.875rem; font-weight: 500; color: var(--ink); }
        .rev-hint { font-size: 0.875rem; line-height: 1.55; color: var(--muted-ink); }
        .rev-note { font-size: 0.875rem; font-weight: 700; color: var(--red-strong); }
        .rev-toggles { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 84px), 1fr)); gap: var(--s-2); }
        .rev-slider-head { display: flex; align-items: baseline; justify-content: space-between; gap: var(--s-3); font-size: 0.9375rem; }
        .rev-actions { display: flex; flex-wrap: wrap; gap: var(--s-2); }
        .rev-cand { display: flex; flex-direction: column; gap: var(--s-3); padding: var(--s-4) 0; border-top: 1.5px dashed var(--border-soft); min-width: 0; }
        .rev-cand:first-of-type { border-top: 0; padding-top: 0; }
        .rev-cand-top { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--s-2) var(--s-4); }
        .rev-score { font-family: var(--font-mono); font-variant-numeric: tabular-nums; font-size: 26px; font-weight: 600; line-height: 1; color: var(--ink); }
        .rev-score small { font-family: var(--font-sans); font-size: 11px; font-weight: 500; color: var(--muted-ink); margin-left: 3px; }
        .rev-reason { font-size: 0.875rem; line-height: 1.6; color: var(--muted-ink); max-width: 72ch; }
        .rev-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--s-2) var(--s-4); }
        .rev-preset { white-space: normal; text-align: left; height: auto; padding: var(--s-2) var(--s-3); line-height: 1.35; }
        .rev-panel input[type=number] { min-width: 0; }

        /* Auto Headline live preview: marker / highlighter / sticker. */
        .rev-headline-preview {
            display: inline-block; max-width: 100%; padding: 2px var(--s-3);
            font-weight: 700; font-size: 1.25rem; line-height: 1.35;
            overflow-wrap: anywhere; text-align: center;
        }
        .rev-hl-marker { color: var(--card); -webkit-text-stroke: 1.5px var(--ink); text-shadow: 0 1px 0 var(--ink); }
        .rev-hl-highlight { color: var(--ink); background: var(--hl); }
        .rev-hl-sticker { color: var(--card); background: var(--red); border-radius: var(--r); }
        .rev-headline-stage {
            padding: var(--s-4); border: 1.5px dashed var(--border-ctl); border-radius: var(--r);
            background: var(--paper-2); text-align: center;
        }
        @media (max-width: 768px) {
            .rev-layout { grid-template-columns: minmax(0, 1fr); }
            .rev-score { font-size: 22px; }
        }
    </style>

    <header class="page-head" style="margin-bottom: 0;">
        <h1>Review &amp; Edit Kandidat</h1>
        <p>Tonton video sumber, lalu putuskan tiap kandidat: setujui, tolak, atau sesuaikan batas waktu sebelum render.</p>
        <div class="rev-meta" style="margin-top: var(--s-1);">
            <span class="muted truncate" style="max-width: 32ch;" title="{{ $video->source_ref }}">{{ $video->source_ref ?? '—' }}</span>
            @if($video->duration_seconds)
                <span class="mono">{{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}</span>
            @endif
            <span class="mono">{{ $candidates->count() }} kandidat</span>
        </div>
    </header>

    <div class="row row-wrap between">
        <button type="button" class="btn btn-secondary" wire:click="createCustomCandidate">Buat klip kustom</button>
        @if($poll)
            <span class="tag" role="status"><span class="spinner" aria-hidden="true"></span> Mengekspor &amp; merender</span>
        @endif
    </div>

    @if($error)
        <div class="callout" role="alert">
            <p class="callout-title">Aksi gagal</p>
            <p>{{ $error }}</p>
        </div>
    @endif

    <div class="rev-layout">

        <div class="rev-col">
            @if($editingId === null)

                <div class="panel tape rev-panel rise" style="--i:0">
                    <div class="section-head" style="margin-bottom: 0;">
                        <h2>Video sumber</h2>
                        @if($video->duration_seconds)
                            <span class="mono muted">{{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}</span>
                        @endif
                    </div>
                    <video id="editor-video" class="rev-video" controls preload="metadata" src="/videos/{{ $video->id }}/source">
                        Browser Anda tidak mendukung HTML5 video.
                    </video>
                    <p class="rev-hint">Pemutar di atas untuk meninjau stempel waktu kandidat. Untuk mengubah batas klip, pakai tombol Edit pada kandidat yang dimaksud.</p>
                    <a href="/openreel/?video_url={{ urlencode('/videos/' . $video->id . '/source') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-block">Edit video di OpenReel</a>
                </div>

                <div class="panel rev-panel rise" style="--i:1">
                    <div class="section-head" style="margin-bottom: 0;">
                        <h2>Gaya caption</h2>
                        <span class="tag tag-dashed">Mood caption</span>
                    </div>

                    <div class="field">
                        <span class="rev-label" id="rev-burn-label">Burn subtitle ke klip</span>
                        <div class="row" role="group" aria-labelledby="rev-burn-label" style="gap: var(--s-2);">
                            <button type="button" class="btn btn-sm {{ $burnSubtitles === 'on' ? '' : 'btn-secondary' }}"
                                    wire:click="$set('burnSubtitles', 'on')"
                                    aria-pressed="{{ $burnSubtitles === 'on' ? 'true' : 'false' }}">ON</button>
                            <button type="button" class="btn btn-sm {{ $burnSubtitles === 'off' ? '' : 'btn-secondary' }}"
                                    wire:click="$set('burnSubtitles', 'off')"
                                    aria-pressed="{{ $burnSubtitles === 'off' ? 'true' : 'false' }}">OFF</button>
                        </div>
                    </div>

                    <div class="field">
                        <span class="rev-label" id="rev-color-label">Preset warna subtitle</span>
                        <div class="row row-wrap" role="group" aria-labelledby="rev-color-label" style="gap: var(--s-2);">
                            @foreach(['yellow' => 'Kuning', 'pink' => 'Merah muda', 'orange' => 'Oranye', 'white' => 'Putih'] as $colorKey => $colorLabel)
                                <button type="button" class="btn btn-sm {{ $subtitleColor === $colorKey ? '' : 'btn-secondary' }}"
                                        wire:click="$set('subtitleColor', '{{ $colorKey }}')"
                                        aria-pressed="{{ $subtitleColor === $colorKey ? 'true' : 'false' }}">{{ $colorLabel }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="field">
                        <span class="rev-label" id="rev-anim-label">Animasi kata per kata</span>
                        <div class="rev-toggles" role="group" aria-labelledby="rev-anim-label">
                            @foreach(['POP', 'FADE', 'RISE', 'DROP', 'ZOOM', 'BLUR', 'TYPE', 'OFF'] as $anim)
                                <button type="button" class="btn btn-sm {{ $captionAnimation === strtolower($anim) ? '' : 'btn-secondary' }}"
                                        wire:click="$set('captionAnimation', '{{ strtolower($anim) }}')">{{ $anim }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="field">
                        <div class="rev-slider-head">
                            <label for="rev-font-size">Ukuran font</label>
                            <span class="mono">{{ $captionFontSize }}</span>
                        </div>
                        <input id="rev-font-size" type="range" min="40" max="120" wire:model.live="captionFontSize">
                    </div>

                    <div class="field">
                        <div class="rev-slider-head">
                            <label for="rev-pos-y">Posisi vertikal caption</label>
                            <span class="mono">{{ $captionPosY }}</span>
                        </div>
                        <input id="rev-pos-y" type="range" min="200" max="1800" wire:model.live="captionPosY">
                    </div>

                    <button type="button" wire:click="batchRenderAll" class="btn btn-block">Render semua klip (file baru)</button>
                </div>

            @else

                <div class="panel rev-panel rise" style="--i:0">
                    <div class="section-head" style="margin-bottom: 0;">
                        <h2>{{ $editingId === -1 ? 'Buat klip kustom' : 'Edit klip #' . $editingId }}</h2>
                    </div>

                    <video id="editor-video" class="rev-video" controls preload="metadata"
                           onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                           src="/videos/{{ $video->id }}/source"></video>

                    <div class="rev-actions">
                        <button type="button" class="btn btn-sm btn-secondary" onclick="setStartToCurrent()" title="Gunakan posisi player saat ini sebagai waktu awal">Set awal</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="setEndToCurrent()" title="Gunakan posisi player saat ini sebagai waktu akhir">Set akhir</button>
                    </div>

                    <div class="rev-actions">
                        <button type="button" class="btn btn-sm" onclick="playPreview()">Preview klip</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="jumpToStart()" title="Lompat ke penanda awal">Awal</button>
                        <button type="button" class="btn btn-sm btn-secondary" onclick="jumpToEnd()" title="Lompat ke penanda akhir">Akhir</button>
                    </div>

                    <div class="row" style="gap: var(--s-3); align-items: flex-start;">
                        <div class="field grow">
                            <label for="rev-start">Start (ms)</label>
                            <input id="rev-start" type="number" class="mono" wire:model.live="editStartMs">
                        </div>
                        <div class="field grow">
                            <label for="rev-end">End (ms)</label>
                            <input id="rev-end" type="number" class="mono" wire:model.live="editEndMs">
                        </div>
                    </div>

                    <div class="field">
                        <div class="rev-slider-head">
                            <label for="rev-hook">Hook score</label>
                            <span class="mono">{{ $editHookScore }}%</span>
                        </div>
                        <input id="rev-hook" type="range" min="0" max="100" wire:model="editHookScore">
                    </div>

                    <div class="field">
                        <label for="rev-rationale">Deskripsi / rationale</label>
                        <textarea id="rev-rationale" rows="3" wire:model="editRationale"></textarea>
                    </div>

                    <div class="rev-actions">
                        <button type="button" class="btn btn-sm btn-secondary" wire:click="closeEditor">Batal</button>
                        <button type="button" class="btn btn-sm" wire:click="{{ $editingId === -1 ? 'saveCustomCandidate' : 'saveCandidate' }}">Simpan</button>
                    </div>
                </div>

            @endif
        </div>

        <div class="rev-col">

            <div class="panel rev-panel rise" style="--i:1">
                <div class="section-head" style="margin-bottom: 0;">
                    <h2>Pengaturan ekspor &amp; subtitle</h2>
                    <span class="tag tag-dashed">Dipakai saat approve</span>
                </div>
                <p class="rev-hint">Teks CTA, layout, dan gaya subtitle dipakai saat kandidat disetujui.</p>

                <div class="field">
                    <label for="rev-cta">Teks call-to-action (CTA)</label>
                    <input id="rev-cta" type="text" wire:model="ctaText" maxlength="120"
                           placeholder="Masukkan CTA khusus (cth. BELI SEKARANG DI STEAM)">
                    @if(trim($ctaText) === '')
                        <p class="rev-note">Teks CTA kosong. Direkomendasikan mengisi CTA.</p>
                    @endif
                </div>

                <div class="field">
                    <span class="rev-label" id="rev-preset-label">Preset cepat</span>
                    <div class="rev-actions" role="group" aria-labelledby="rev-preset-label">
                        @foreach($this->ctaPresets() as $preset)
                            <button type="button" class="btn btn-sm btn-secondary rev-preset"
                                    wire:click="$set('ctaText', @js($preset))">{{ $revPlain($preset) }}</button>
                        @endforeach
                    </div>
                </div>

                <div class="field">
                    <label for="rev-layout">Layout video</label>
                    <select id="rev-layout" wire:model="layout">
                        <option value="single">Single Speaker Crop (9:16 Face Tracking)</option>
                        <option value="split_gaming">Gaming Split-Screen (Facecam Atas + Gameplay Bawah)</option>
                    </select>
                </div>

                <div class="field">
                    <label for="rev-caption-style">Gaya auto-caption</label>
                    <select id="rev-caption-style" wire:model="captionStyle">
                        <option value="default">Default (Putih Arial)</option>
                        <option value="none">Tanpa Caption (No Caption)</option>
                        <option value="karaoke_yellow">Karaoke Yellow (Kuning Arial)</option>
                        <option value="tiktok_green">TikTok Green (Hijau Arial Black)</option>
                        <option value="short_bold">Short Bold (Putih Impact)</option>
                        <option value="hormozi_neon">Alex Hormozi (Neon Segoe UI Black)</option>
                        <option value="mrbeast_comic">MrBeast (Kuning Impact + Bold Outline)</option>
                        <option value="minimal_outfit">Ali Abdaal (Minimalis Trebuchet MS)</option>
                    </select>
                </div>

                <div class="field">
                    <div class="rev-slider-head">
                        <label for="rev-margin-v">Posisi vertikal caption</label>
                        <span class="mono">{{ $captionMarginV }} px dari bawah</span>
                    </div>
                    <input id="rev-margin-v" type="range" min="40" max="1200" step="10" wire:model.live="captionMarginV">
                    <p class="rev-hint">Geser ke kanan untuk memindahkan teks ke arah tengah/atas layar (tinggi total 1920 px).</p>
                </div>
            </div>

            <div class="panel rev-panel rise" style="--i:2" aria-labelledby="rev-extras-title">
                <div class="section-head" style="margin-bottom: 0;">
                    <h2 id="rev-extras-title">Judul &amp; musik latar</h2>
                    <span class="tag">Opsional</span>
                </div>

                <div class="field">
                    <label for="rev-headline">Auto Headline (tampil 5 detik pertama)</label>
                    <input id="rev-headline" type="text" wire:model.live="headlineText" maxlength="80"
                           placeholder="Judul singkat untuk hook klip">
                    <div class="row between">
                        <p class="field-hint">Maksimal 80 karakter. Kosongkan kalau klip tidak perlu judul.</p>
                        <span class="mono muted">{{ mb_strlen($headlineText) }}/80</span>
                    </div>
                    @error('headlineText') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label for="rev-headline-style">Gaya judul</label>
                    <select id="rev-headline-style" wire:model.live="headlineStyle">
                        @foreach($headlineStyles as $styleKey => $style)
                            <option value="{{ $styleKey }}">{{ $style['label'] }}</option>
                        @endforeach
                    </select>
                </div>

                <div class="field">
                    <span class="rev-label">Pratinjau judul</span>
                    <div class="rev-headline-stage">
                        <span class="rev-headline-preview {{ $headlineStyleClass }}">{{ trim($headlineText) !== '' ? $headlineText : 'Pratinjau judul klip' }}</span>
                    </div>
                </div>

                <div class="field">
                    <label for="rev-music">Musik latar</label>
                    <select id="rev-music" wire:model.live="musicAssetId">
                        <option value="">Tanpa musik</option>
                        @foreach($musicAssets as $asset)
                            <option value="{{ $asset->id }}">{{ $asset->name }}</option>
                        @endforeach
                    </select>
                    @error('musicAssetId') <p class="field-error">{{ $message }}</p> @enderror
                    @if($musicAssets->isEmpty())
                        <p class="field-hint">Belum ada trek musik. <a href="/assets" wire:navigate>Unggah musik di Aset</a>.</p>
                    @endif
                </div>

                <div class="field">
                    <div class="rev-slider-head">
                        <label for="rev-music-volume">Volume musik</label>
                        <span class="mono">{{ $musicVolume }}%</span>
                    </div>
                    <input id="rev-music-volume" type="range" min="0" max="60" step="1"
                           wire:model.live="musicVolume" @disabled($musicAssetId === null)>
                    <p class="rev-hint">
                        @if($musicAssetId === null)
                            Pilih trek musik dulu untuk mengatur volume.
                        @else
                            Volume relatif terhadap audio asli klip.
                        @endif
                    </p>
                    @error('musicVolume') <p class="field-error">{{ $message }}</p> @enderror
                </div>
            </div>

            <div class="rev-col" style="gap: var(--s-3);">
                <div class="section-head" style="margin-bottom: 0;">
                    <h2>Kandidat klip</h2>
                    <span class="mono muted">{{ $candidates->count() }}</span>
                </div>

                @forelse($candidates as $c)
                    @php
                        $dur = ($c->end_ms - $c->start_ms) / 1000;
                        [$sCls, $sLabel, $sMark] = match ($c->status) {
                            'approved' => ['tag tag-solid', 'Disetujui · Rendering', 'mark mark-on'],
                            'exported' => ['tag tag-solid', 'Selesai Diekspor', 'mark mark-on'],
                            'rejected' => ['tag tag-dashed', 'Ditolak', 'mark'],
                            default => ['tag', 'Menunggu Tinjauan', 'mark'],
                        };
                        $fmt = fn ($ms) => gmdate('i:s', intdiv($ms, 1000));
                    @endphp

                    <article class="rev-cand" wire:key="cand-{{ $c->id }}">
                        <div class="rev-cand-top">
                            <span class="rev-score">{{ $c->hook_score }}<small>score</small></span>
                            <span class="mono">{{ $fmt($c->start_ms) }}–{{ $fmt($c->end_ms) }}</span>
                            <span class="mono muted">{{ number_format($dur, 1) }} detik</span>
                            <span class="{{ $sCls }}"><span class="{{ $sMark }}" aria-hidden="true"></span>{{ $sLabel }}</span>
                        </div>

                        @if($c->score_rationale)
                            <p class="rev-reason">{{ $c->score_rationale }}</p>
                        @endif

                        <div class="rev-actions">
                            @if(in_array($c->status, ['pending', 'rejected']))
                                <button type="button" class="btn btn-sm"
                                        wire:click="approve({{ $c->id }})"
                                        wire:loading.attr="disabled" wire:target="approve({{ $c->id }})">Approve</button>
                            @endif
                            @if(in_array($c->status, ['pending', 'approved']))
                                <button type="button" class="btn btn-sm btn-secondary"
                                        wire:click="reject({{ $c->id }})"
                                        wire:loading.attr="disabled" wire:target="reject({{ $c->id }})">Reject</button>
                            @endif
                            @if(in_array($c->status, ['pending', 'rejected']))
                                <a href="/candidates/{{ $c->id }}/edit" wire:navigate class="btn btn-sm btn-secondary"
                                   title="Buka editor klip untuk kandidat ini">Edit</a>
                            @endif
                        </div>
                    </article>
                @empty
                    <div class="empty">
                        <svg class="doodle" viewBox="0 0 72 72" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                            <path d="M8 20h56v34H8z" />
                            <path d="M8 27h56M8 47h56" />
                            <path d="M16 27v-6M24 27v-6M32 27v-6M40 27v-6M48 27v-6M56 27v-6" />
                            <path d="M16 54v6M30 54v6M44 54v6M58 54v6" />
                        </svg>
                        <p>Belum ada kandidat klip. Pipeline transkripsi dan scoring mungkin masih berjalan; daftar akan dimuat ulang otomatis.</p>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="$refresh">Muat ulang daftar</button>
                    </div>
                @endforelse
            </div>

        </div>
    </div>

    <!-- Video Editor Script Binding -->
    <script>
        function extractStartMs(detail) {
            if (!detail) return 0;
            if (typeof detail.startMs !== 'undefined') return detail.startMs;
            if (Array.isArray(detail) && detail[0] && typeof detail[0].startMs !== 'undefined') return detail[0].startMs;
            return 0;
        }

        function seekEditorVideoTo(ms) {
            if (typeof ms === 'undefined' || ms === null || ms < 0) return;
            const targetSeconds = ms / 1000;

            const trySeek = () => {
                const video = document.getElementById('editor-video');
                if (!video) return false;

                const performSeek = () => {
                    try {
                        video.currentTime = targetSeconds;
                    } catch (err) {}
                };

                if (video.readyState >= 1) { // HAVE_METADATA or higher
                    performSeek();
                } else {
                    video.addEventListener('loadedmetadata', performSeek, { once: true });
                    video.addEventListener('canplay', performSeek, { once: true });
                    setTimeout(performSeek, 150);
                    setTimeout(performSeek, 400);
                }
                return true;
            };

            if (!trySeek()) {
                setTimeout(trySeek, 100);
                setTimeout(trySeek, 300);
                setTimeout(trySeek, 600);
            }
        }

        // Listen for candidate loading events from Livewire
        if (!window.candidateListenerRegistered) {
            window.addEventListener('candidate-selected', (event) => {
                const startMs = extractStartMs(event.detail);
                if (startMs >= 0) {
                    seekEditorVideoTo(startMs);
                }
            });
            window.candidateListenerRegistered = true;
            if (typeof window.previewInterval === 'undefined') {
                window.previewInterval = null;
            }

            window.setStartToCurrent = window.setStartToCurrent || function (comp) {
                const video = document.getElementById('editor-video');
                if (video && comp) {
                    const currentMs = Math.round(video.currentTime * 1000);
                    comp.set('editStartMs', currentMs);
                }
            };

            window.setEndToCurrent = window.setEndToCurrent || function (comp) {
                const video = document.getElementById('editor-video');
                if (video && comp) {
                    const currentMs = Math.round(video.currentTime * 1000);
                    comp.set('editEndMs', currentMs);
                }
            };

            window.jumpToStart = window.jumpToStart || function (startMs) {
                seekEditorVideoTo(startMs);
            };

            window.jumpToEnd = window.jumpToEnd || function (endMs) {
                seekEditorVideoTo(endMs);
            };

            window.playPreview = window.playPreview || function (startMs, endMs) {
                const video = document.getElementById('editor-video');
                if (video) {
                    video.currentTime = (startMs || 0) / 1000;
                    video.play();

                    if (window.previewInterval) clearInterval(window.previewInterval);

                    window.previewInterval = setInterval(() => {
                        const currentMs = video.currentTime * 1000;
                        if (currentMs >= endMs) {
                            video.pause();
                            clearInterval(window.previewInterval);
                        }
                    }, 50);
                }
            };
        }
    </script>
</div>
