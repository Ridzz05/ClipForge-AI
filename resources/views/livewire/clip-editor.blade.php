@php
    $segCount = count($segments);
@endphp

<div class="edit-page">
    <nav class="edit-crumbs" aria-label="Breadcrumb">
        <a href="/" wire:navigate>Dashboard</a>
        <span aria-hidden="true">/</span>
        <a href="/videos/{{ $candidate->video_id }}/review" wire:navigate>Review Video #{{ $candidate->video_id }}</a>
        <span aria-hidden="true">/</span>
        <span aria-current="page">Editor Klip #{{ $candidate->id }}</span>
    </nav>

    <header class="page-head">
        <div class="row between row-wrap">
            <div>
                <h1>Editor Klip #{{ $candidate->id }}</h1>
                <p>Atur trim, framing, dan caption. Semua perubahan baru dipakai saat klip dirender.</p>
            </div>
            <div class="row row-wrap">
                <a href="/videos/{{ $candidate->video_id }}/review" wire:navigate class="btn btn-secondary btn-sm">Kembali</a>
                <a href="/openreel/?video_url={{ urlencode('/videos/' . $candidate->video_id . '/source') }}" target="_blank" rel="noopener" class="btn btn-secondary btn-sm">Edit di OpenReel</a>
            </div>
        </div>

        @if($error)
            <div class="callout" role="alert">
                <strong class="callout-title">Gagal menyimpan</strong>
                <p class="edit-hint">{{ $error }}</p>
            </div>
        @endif
    </header>

    <div class="edit-layout">
        <!-- Stage: preview + trim + segments -->
        <div class="edit-stage">
            <section class="panel tape rise" style="--i:0" aria-labelledby="edit-preview-title">
                <div class="section-head">
                    <h2 id="edit-preview-title">Pratinjau</h2>
                    <span class="tag mono">{{ $cropMode === 'auto' ? 'AUTO' : 'MANUAL' }}</span>
                </div>

                <!-- Framing mode -->
                <div class="edit-tools">
                    <button type="button"
                            wire:click="runAutoFramingAI"
                            wire:loading.attr="disabled"
                            wire:target="runAutoFramingAI"
                            aria-pressed="{{ $cropMode === 'auto' ? 'true' : 'false' }}"
                            title="Deteksi wajah lalu geser framing mengikuti pembicara"
                            class="edit-seg">
                        <span wire:loading.remove wire:target="runAutoFramingAI">Auto framing AI</span>
                        <span wire:loading wire:target="runAutoFramingAI" class="row">
                            <span class="spinner" aria-hidden="true"></span> Menganalisis wajah
                        </span>
                    </button>

                    <button type="button"
                            wire:click="setPodcastLeftSpeaker"
                            title="Pangkas ke 25% dari kiri"
                            aria-pressed="{{ $cropMode === 'manual' && abs($manualCropX - 0.25) < 0.05 ? 'true' : 'false' }}"
                            class="edit-seg">Kiri 25%</button>

                    <button type="button"
                            wire:click="setPodcastCenter"
                            title="Pangkas ke tengah"
                            aria-pressed="{{ $cropMode === 'manual' && abs($manualCropX - 0.50) < 0.05 ? 'true' : 'false' }}"
                            class="edit-seg">Tengah 50%</button>

                    <button type="button"
                            wire:click="setPodcastRightSpeaker"
                            title="Pangkas ke 25% dari kanan"
                            aria-pressed="{{ $cropMode === 'manual' && abs($manualCropX - 0.75) < 0.05 ? 'true' : 'false' }}"
                            class="edit-seg">Kanan 75%</button>

                    <button type="button"
                            wire:click="$set('cropMode', 'manual')"
                            title="Seret bingkai secara manual"
                            aria-pressed="{{ $cropMode === 'manual' && abs($manualCropX - 0.25) >= 0.05 && abs($manualCropX - 0.50) >= 0.05 && abs($manualCropX - 0.75) >= 0.05 ? 'true' : 'false' }}"
                            class="edit-seg">Manual</button>
                </div>

                @if($cropMode === 'auto')
                    <!-- 9:16 live canvas -->
                    <div id="live-canvas-frame" class="edit-canvas">
                        @if($renderFormat === 'split_podcast')
                            <div class="edit-canvas-frames">
                                <div class="edit-canvas-half edit-canvas-half-top">
                                    <video id="editor-video-top" preload="metadata" muted
                                           class="edit-canvas-video"
                                           style="object-position: {{ $splitTopCropX * 100 }}% center"
                                           src="/videos/{{ $candidate->video_id }}/source"></video>
                                    <span class="edit-frame-label">Atas {{ round($splitTopCropX * 100) }}%</span>
                                </div>
                                <div class="edit-canvas-half">
                                    <video id="editor-video-bottom" preload="metadata" muted
                                           class="edit-canvas-video"
                                           style="object-position: {{ $splitBottomCropX * 100 }}% center"
                                           src="/videos/{{ $candidate->video_id }}/source"></video>
                                    <span class="edit-frame-label">Bawah {{ round($splitBottomCropX * 100) }}%</span>
                                </div>
                            </div>
                        @else
                            <video id="editor-video" class="edit-canvas-video edit-canvas-fill" controls preload="metadata"
                                   onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                                   src="/videos/{{ $candidate->video_id }}/source"></video>
                        @endif

                        @if($burnSubtitles === 'on')
                            <div id="subtitle-overlay" class="edit-caption-overlay" style="bottom: {{ ($captionPosY / 1920) * 100 }}%;">
                                <div id="caption-word-group"
                                     data-animation="{{ $captionAnimation }}"
                                     data-color="{{ $subtitleColor }}"
                                     data-fontsize="{{ ($captionFontSize / 1920) * 450 }}"
                                     style="
                                         font-family: '{{ $captionFont }}', sans-serif;
                                         font-size: {{ max(12, (int) round(($captionFontSize / 1920) * 450)) }}px;
                                         font-weight: 800;
                                         text-transform: uppercase;
                                         line-height: 1.3;
                                         display: flex;
                                         flex-wrap: wrap;
                                         justify-content: center;
                                         gap: 0 4px;
                                         min-height: 1.5em;
                                     ">
                                    <!-- JS renders word spans here -->
                                </div>
                            </div>
                        @endif
                    </div>

                    @if($renderFormat === 'split_podcast')
                        <!-- Driver: audio + transport + timeline sync for the two frames above -->
                        <video id="editor-video" class="edit-canvas-driver" controls preload="metadata"
                               onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                               onplay="document.getElementById('editor-video-top').play(); document.getElementById('editor-video-bottom').play();"
                               onpause="document.getElementById('editor-video-top').pause(); document.getElementById('editor-video-bottom').pause();"
                               onseeking="document.getElementById('editor-video-top').currentTime = this.currentTime; document.getElementById('editor-video-bottom').currentTime = this.currentTime;"
                               src="/videos/{{ $candidate->video_id }}/source"></video>
                    @endif
                @else
                    <!-- 16:9 source + draggable 9:16 crop window -->
                    <div class="edit-crop-wrap">
                        <div id="manual-crop-container"
                             wire:ignore.self
                             class="edit-crop-stage"
                             onclick="positionManualCropOnClick(event)">
                            <video id="editor-video-manual" preload="metadata"
                                   class="edit-crop-source"
                                   onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                                   src="/videos/{{ $candidate->video_id }}/source"></video>

                            <div id="manual-crop-window"
                                 class="edit-crop-window"
                                 onmousedown="startManualCropDrag(event)"
                                 ontouchstart="startManualCropDrag(event)"
                                 style="left: calc({{ $manualCropX * 100 }}% - 15.82%);">
                                <div class="edit-crop-window-head">Bingkai 9:16</div>

                                @if($burnSubtitles === 'on')
                                    <div id="subtitle-overlay-manual" class="edit-caption-overlay" style="bottom: {{ ($captionPosY / 1920) * 100 }}%;">
                                        <div id="caption-word-group-manual"
                                             data-animation="{{ $captionAnimation }}"
                                             data-color="{{ $subtitleColor }}"
                                             data-fontsize="{{ ($captionFontSize / 1920) * 315 }}"
                                             style="
                                                 font-family: '{{ $captionFont }}', sans-serif;
                                                 font-size: {{ max(10, (int) round(($captionFontSize / 1920) * 315)) }}px;
                                                 font-weight: 800;
                                                 text-transform: uppercase;
                                                 line-height: 1.3;
                                                 display: flex;
                                                 flex-wrap: wrap;
                                                 justify-content: center;
                                                 gap: 0 3px;
                                                 min-height: 1.5em;
                                             ">
                                            <!-- JS syncs word spans here -->
                                        </div>
                                    </div>
                                @endif

                                <div class="edit-crop-window-foot">
                                    X <span id="manual-crop-x-label" class="mono">{{ round($manualCropX * 100, 1) }}%</span>
                                </div>
                            </div>
                        </div>

                        <div class="edit-crop-range">
                            <label for="manual-crop-range" class="edit-label">Posisi bingkai</label>
                            <input type="range"
                                   id="manual-crop-range"
                                   min="0"
                                   max="1"
                                   step="0.002"
                                   value="{{ $manualCropX }}"
                                   oninput="window.updateCropBoxUI(parseFloat(this.value))">
                        </div>

                        <p class="edit-hint">Geser bingkai di atas video, atau pakai slider. Bisa juga tombol Kiri/Tengah/Kanan.</p>
                    </div>
                @endif

                <!-- Transport -->
                <div class="edit-transport">
                    <button type="button" class="btn"
                            onclick="playPreview(Livewire.find('{{ $this->getId() }}').get('editStartMs'), Livewire.find('{{ $this->getId() }}').get('editEndMs'))">
                        Play loop
                    </button>
                    <button type="button" class="btn btn-secondary"
                            onclick="jumpToStart(Livewire.find('{{ $this->getId() }}').get('editStartMs'))">Ke awal</button>
                    <button type="button" class="btn btn-secondary"
                            onclick="jumpToEnd(Livewire.find('{{ $this->getId() }}').get('editEndMs'))">Ke akhir</button>
                </div>
            </section>

            <!-- Trim timeline -->
            <section class="panel rise" style="--i:1" aria-labelledby="edit-trim-title">
                <div class="section-head">
                    <h2 id="edit-trim-title">Trim</h2>
                    <span id="timeline-duration" class="tag mono">DUR: --:--</span>
                </div>

                <div class="edit-trim-labels">
                    <div class="edit-trim-readout">
                        <span class="edit-label">Mulai</span>
                        <span id="tl-start-label" class="mono edit-time">00:00</span>
                    </div>
                    <div class="edit-trim-readout edit-trim-readout-end">
                        <span class="edit-label">Selesai</span>
                        <span id="tl-end-label" class="mono edit-time">00:00</span>
                    </div>
                </div>

                <div id="timeline-track" class="edit-track">
                    <div id="tl-selection" class="edit-track-selection"></div>
                    <div id="tl-playhead" class="edit-track-playhead"></div>
                    <div id="tl-handle-start" class="edit-handle edit-handle-start" title="Geser untuk atur mulai"></div>
                    <div id="tl-handle-end" class="edit-handle edit-handle-end" title="Geser untuk atur selesai"></div>
                </div>

                <div class="row row-wrap">
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="setStartToCurrent(Livewire.find('{{ $this->getId() }}'))">Set mulai di playhead</button>
                    <button type="button" class="btn btn-secondary btn-sm"
                            onclick="setEndToCurrent(Livewire.find('{{ $this->getId() }}'))">Set selesai di playhead</button>
                </div>
            </section>

            <!-- Segments -->
            <section class="panel rise" style="--i:2" aria-labelledby="edit-segments-title">
                <div class="section-head">
                    <h2 id="edit-segments-title">Split &amp; jump-cut</h2>
                    <span class="tag mono">{{ $segCount }} segmen</span>
                </div>

                <p class="edit-hint">Buang bagian filler di dalam klip. Segmen digabung otomatis saat ekspor.</p>

                <button type="button" onclick="splitAtCurrentPlayhead()" class="btn btn-secondary btn-block">
                    Potong di playhead
                </button>

                <div class="edit-seg-list">
                    @foreach($segments as $idx => $seg)
                        @php
                            $segDurSec = round(($seg['end_ms'] - $seg['start_ms']) / 1000);
                            $segStartSec = $seg['start_ms'] / 1000;
                            $segEndSec = $seg['end_ms'] / 1000;
                        @endphp
                        <div class="edit-seg-row">
                            <span class="edit-seg-index mono">{{ $idx + 1 }}</span>
                            <div class="grow">
                                <div class="mono">
                                    {{ sprintf('%02d:%02d', floor($segStartSec / 60), $segStartSec % 60) }}
                                    <span class="muted">&rarr;</span>
                                    {{ sprintf('%02d:%02d', floor($segEndSec / 60), $segEndSec % 60) }}
                                </div>
                                <div class="edit-hint">Durasi {{ $segDurSec }}s</div>
                            </div>
                            <button type="button" wire:click="deleteSegment({{ $idx }})"
                                    class="btn btn-secondary btn-sm"
                                    title="Hapus segmen ini">Hapus</button>
                        </div>
                    @endforeach
                </div>
            </section>
        </div>

        <!-- Inspector -->
        <aside class="edit-inspector">
            <section class="panel rise" style="--i:0" aria-labelledby="edit-format-title">
                <div class="section-head">
                    <h2 id="edit-format-title">Format</h2>
                    <span class="tag mono">{{ strtoupper($renderFormat) }}</span>
                </div>

                <div class="edit-seg-wrap">
                    <button type="button" wire:click="$set('renderFormat', 'face_916')"
                            aria-pressed="{{ $renderFormat === 'face_916' ? 'true' : 'false' }}"
                            class="edit-seg">Single 9:16</button>
                    <button type="button" wire:click="$set('renderFormat', 'split_podcast')"
                            aria-pressed="{{ $renderFormat === 'split_podcast' ? 'true' : 'false' }}"
                            class="edit-seg">Split podcast (atas/bawah)</button>
                </div>
            </section>

            @if($renderFormat === 'split_podcast')
                <section class="panel rise" style="--i:1" aria-labelledby="edit-split-title">
                    <div class="section-head">
                        <h2 id="edit-split-title">Posisi kamera ganda</h2>
                        <span class="tag mono">ATAS &amp; BAWAH</span>
                    </div>

                    <div class="edit-split-block">
                        <div class="row between">
                            <label for="edit-split-top" class="edit-label">Bingkai atas</label>
                            <span class="mono">{{ round($splitTopCropX * 100) }}%</span>
                        </div>
                        <input type="range" id="edit-split-top" min="0.0" max="1.0" step="0.005"
                               wire:model.live="splitTopCropX">
                        <div class="edit-seg-wrap">
                            <button type="button" wire:click="$set('splitTopCropX', 0.25)" class="edit-seg">Kiri 25%</button>
                            <button type="button" wire:click="$set('splitTopCropX', 0.50)" class="edit-seg">Tengah 50%</button>
                            <button type="button" wire:click="$set('splitTopCropX', 0.75)" class="edit-seg">Kanan 75%</button>
                        </div>
                    </div>

                    <div class="edit-split-block">
                        <div class="row between">
                            <label for="edit-split-bottom" class="edit-label">Bingkai bawah</label>
                            <span class="mono">{{ round($splitBottomCropX * 100) }}%</span>
                        </div>
                        <input type="range" id="edit-split-bottom" min="0.0" max="1.0" step="0.005"
                               wire:model.live="splitBottomCropX">
                        <div class="edit-seg-wrap">
                            <button type="button" wire:click="$set('splitBottomCropX', 0.25)" class="edit-seg">Kiri 25%</button>
                            <button type="button" wire:click="$set('splitBottomCropX', 0.50)" class="edit-seg">Tengah 50%</button>
                            <button type="button" wire:click="$set('splitBottomCropX', 0.75)" class="edit-seg">Kanan 75%</button>
                        </div>
                    </div>
                </section>
            @endif

            <section class="panel tape rise" style="--i:2" aria-labelledby="edit-caption-title">
                <div class="section-head">
                    <h2 id="edit-caption-title">Caption</h2>
                    <span class="tag mono">{{ $burnSubtitles === 'on' ? 'AKTIF' : 'NONAKTIF' }}</span>
                </div>

                <div class="edit-fields">
                    <div class="field">
                        <div class="row between">
                            <label for="edit-language">Bahasa terjemahan</label>
                            <span wire:loading wire:target="updatedTargetLanguage" class="row edit-hint">
                                <span class="spinner" aria-hidden="true"></span> Menerjemahkan
                            </span>
                        </div>
                        <select id="edit-language" wire:model.live="targetLanguage">
                            @foreach(\App\Services\TranslationService::LANGUAGES as $code => $name)
                                <option value="{{ $code }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <label id="edit-burn-label">Burn subtitle</label>
                        <div class="edit-seg-wrap" role="group" aria-labelledby="edit-burn-label">
                            <button type="button" wire:click="$set('burnSubtitles', 'on')"
                                    aria-pressed="{{ $burnSubtitles === 'on' ? 'true' : 'false' }}"
                                    class="edit-seg">On</button>
                            <button type="button" wire:click="$set('burnSubtitles', 'off')"
                                    aria-pressed="{{ $burnSubtitles === 'off' ? 'true' : 'false' }}"
                                    class="edit-seg">Off</button>
                        </div>
                    </div>

                    <div class="field">
                        <label for="edit-font">Font subtitle</label>
                        <select id="edit-font" wire:model.live="captionFont">
                            <option value="Outfit">Outfit</option>
                            <option value="Plus Jakarta Sans">Plus Jakarta Sans</option>
                            <option value="Poppins">Poppins</option>
                            <option value="Montserrat">Montserrat</option>
                            <option value="Inter">Inter</option>
                            <option value="Fira Sans">Fira Sans</option>
                        </select>
                    </div>

                    <div class="field">
                        <label id="edit-color-label">Warna caption</label>
                        <div class="edit-seg-wrap" role="group" aria-labelledby="edit-color-label">
                            @foreach(['yellow' => 'Kuning', 'pink' => 'Merah muda', 'orange' => 'Oranye', 'white' => 'Putih'] as $key => $label)
                                <button type="button" wire:click="$set('subtitleColor', '{{ $key }}')"
                                        aria-pressed="{{ $subtitleColor === $key ? 'true' : 'false' }}"
                                        class="edit-seg">
                                    <span class="mark {{ $subtitleColor === $key ? 'mark-on' : '' }}" aria-hidden="true"></span>
                                    {{ $label }}
                                </button>
                            @endforeach
                        </div>
                        <p class="field-hint">Pratinjau di sini monokrom; warna asli diterapkan saat render.</p>
                    </div>

                    <div class="field">
                        <label id="edit-anim-label">Animasi</label>
                        <div class="edit-seg-grid" role="group" aria-labelledby="edit-anim-label">
                            @foreach(['KARAOKE', 'POP', 'GLOW', 'RISE', 'BLOCK', 'OFF'] as $anim)
                                <button type="button" wire:click="$set('captionAnimation', '{{ strtolower($anim) }}')"
                                        aria-pressed="{{ $captionAnimation === strtolower($anim) ? 'true' : 'false' }}"
                                        class="edit-seg">{{ $anim }}</button>
                            @endforeach
                        </div>
                    </div>

                    <div class="field">
                        <div class="row between">
                            <label for="edit-font-size">Ukuran font</label>
                            <span class="mono">{{ $captionFontSize }}</span>
                        </div>
                        <input type="range" id="edit-font-size" min="40" max="120"
                               wire:model.live.debounce.300ms="captionFontSize">
                    </div>

                    <div class="field">
                        <div class="row between">
                            <label for="edit-pos-y">Posisi Y</label>
                            <span class="mono">{{ $captionPosY }}</span>
                        </div>
                        <input type="range" id="edit-pos-y" min="200" max="1800"
                               wire:model.live.debounce.300ms="captionPosY">
                    </div>

                    @if(count($clipWords) === 0)
                        <div class="empty">
                            <p>Tidak ada kata transkrip pada rentang ini. Caption tidak akan tampil di klip; geser trim ke area yang bertranskrip.</p>
                        </div>
                    @endif

                    <div class="field">
                        <label for="edit-cta">Call to action</label>
                        <input type="text" id="edit-cta" wire:model="ctaText" placeholder="Teks CTA di klip">
                    </div>

                    <div class="field">
                        <label for="edit-cta-preset">Preset CTA</label>
                        <select id="edit-cta-preset" wire:model.live="ctaText">
                            <option value="">Pilih preset</option>
                            @foreach($this->ctaPresets() as $preset)
                                <option value="{{ $preset }}">{{ $preset }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
            </section>

            <section class="panel rise" style="--i:3" aria-labelledby="edit-headline-title">
                <div class="section-head">
                    <h2 id="edit-headline-title">Judul &amp; musik latar</h2>
                    <span class="tag">Opsional</span>
                </div>

                <div class="edit-fields">
                    <div class="field">
                        <label for="edit-headline">Auto Headline (5 detik pertama)</label>
                        <input type="text" id="edit-headline" wire:model.live="headlineText" maxlength="80"
                               placeholder="Judul singkat untuk hook klip">
                        <div class="row between">
                            <p class="field-hint">Maksimal 80 karakter. Kosongkan kalau tidak perlu judul.</p>
                            <span class="mono muted">{{ mb_strlen($headlineText) }}/80</span>
                        </div>
                        @error('headlineText') <p class="field-error">{{ $message }}</p> @enderror
                    </div>

                    <div class="field">
                        <label for="edit-headline-style">Gaya judul</label>
                        <select id="edit-headline-style" wire:model.live="headlineStyle">
                            @foreach($headlineStyles as $styleKey => $style)
                                <option value="{{ $styleKey }}">{{ $style['label'] }}</option>
                            @endforeach
                        </select>
                    </div>

                    <div class="field">
                        <span class="edit-label">Pratinjau judul</span>
                        <div class="edit-headline-stage">
                            <span class="edit-headline-preview {{ match ($headlineStyle) {
                                'highlight' => 'edit-hl-highlight',
                                'sticker' => 'edit-hl-sticker',
                                default => 'edit-hl-marker',
                            } }}">{{ trim($headlineText) !== '' ? $headlineText : 'Pratinjau judul klip' }}</span>
                        </div>
                    </div>

                    <div class="field">
                        <label for="edit-music">Musik latar</label>
                        <select id="edit-music" wire:model.live="musicAssetId">
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
                        <div class="row between">
                            <label for="edit-music-volume">Volume musik</label>
                            <span class="mono">{{ $musicVolume }}%</span>
                        </div>
                        <input type="range" id="edit-music-volume" min="0" max="60" step="1"
                               wire:model.live="musicVolume" @disabled($musicAssetId === null)>
                        <p class="field-hint">
                            @if($musicAssetId === null)
                                Pilih trek musik dulu untuk mengatur volume.
                            @else
                                Volume relatif terhadap audio asli klip.
                            @endif
                        </p>
                        @error('musicVolume') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <button type="button" wire:click="saveAndApprove" class="btn btn-block">
                    Export &amp; Render Klip Ini
                </button>
            </section>
        </aside>
    </div>

    <style>
        /* ---------- Page frame ---------- */
        .edit-page { display: flex; flex-direction: column; gap: var(--s-5); }

        .edit-crumbs { display: flex; flex-wrap: wrap; align-items: center; gap: var(--s-2); font-size: 12px; color: var(--muted-ink); }
        .edit-crumbs a { color: var(--muted-ink); }
        .edit-crumbs a:hover { color: var(--ink); }
        .edit-crumbs span[aria-current="page"] { color: var(--ink); font-weight: 600; }

        .edit-hint { font-size: 12px; color: var(--muted-ink); line-height: 1.45; }
        .edit-label { font-size: 12px; color: var(--muted-ink); font-weight: 500; }
        .edit-time { font-size: 16px; font-weight: 650; }

        .edit-layout { display: grid; grid-template-columns: minmax(0, 1fr) 340px; gap: var(--s-5); align-items: start; }
        .edit-stage, .edit-inspector { display: flex; flex-direction: column; gap: var(--s-5); min-width: 0; }

        /* ---------- Segmented controls (toggle, not action) ---------- */
        .edit-seg {
            display: inline-flex; align-items: center; justify-content: center; gap: 6px;
            min-height: 34px; padding: 0 var(--s-3);
            background: transparent; color: var(--ink);
            border: 1px solid var(--border-ctl); border-radius: var(--r);
            font: 600 12px/1 var(--font-sans); cursor: pointer;
        }
        .edit-seg:hover { background: var(--paper-2); }
        .edit-seg[aria-pressed="true"] { background: var(--ink); color: var(--paper); border-color: var(--ink); }
        .edit-seg[aria-pressed="true"]:hover { background: var(--graphite); }
        @media (pointer: coarse) { .edit-seg { min-height: 44px; } }

        .edit-tools, .edit-seg-wrap { display: flex; flex-wrap: wrap; gap: var(--s-2); }
        .edit-seg-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 84px), 1fr)); gap: var(--s-2); }
        .edit-seg-list { display: flex; flex-direction: column; gap: var(--s-2); margin-top: var(--s-4); }
        .edit-seg-row { display: flex; align-items: center; gap: var(--s-3); padding: var(--s-2) var(--s-3); border: 1px solid var(--border-soft); }
        .edit-seg-index {
            flex: none; width: 22px; height: 22px; display: inline-flex; align-items: center; justify-content: center;
            border: 1px solid var(--border-ctl); border-radius: var(--r); font-size: 11px;
        }

        /* ---------- Canvas ---------- */
        .edit-canvas {
            position: relative; display: flex; flex-direction: column;
            width: 100%; max-width: 280px; margin: 0 auto;
            aspect-ratio: 9 / 16; overflow: hidden;
            background: var(--paper-2); border: 1px solid var(--border-ctl);
        }
        .edit-canvas-video { display: block; width: 100%; height: 100%; object-fit: cover; }
        .edit-canvas-fill { flex: 1 1 auto; min-height: 0; height: auto; }
        .edit-canvas-frames { display: flex; flex-direction: column; flex: 1 1 auto; min-height: 0; }
        .edit-canvas-half { position: relative; flex: 1 1 50%; min-height: 0; overflow: hidden; }
        .edit-canvas-half-top { border-bottom: 1px solid var(--border-ctl); }
        .edit-canvas-driver {
            display: block; flex: none; width: 100%; height: 44px;
            background: var(--paper); border-top: 1px solid var(--border-soft);
        }
        .edit-frame-label {
            position: absolute; top: var(--s-1); left: var(--s-1);
            padding: 1px 5px; background: var(--ink); color: var(--paper);
            font: 600 10px/1.5 var(--font-mono);
        }

        /* ---------- Manual crop ---------- */
        .edit-crop-wrap { display: flex; flex-direction: column; gap: var(--s-3); }
        .edit-crop-stage {
            position: relative; width: 100%; max-width: 560px; margin: 0 auto;
            aspect-ratio: 16 / 9; overflow: hidden; user-select: none;
            background: var(--paper-2); border: 1px solid var(--border-ctl);
        }
        .edit-crop-source { display: block; width: 100%; height: 100%; object-fit: contain; pointer-events: none; }
        .edit-crop-window {
            position: absolute; top: 0; bottom: 0; width: 31.64%;
            display: flex; flex-direction: column; justify-content: space-between;
            border: 2px solid var(--ink); background: transparent;
            cursor: grab; touch-action: none; z-index: 10;
        }
        .edit-crop-window-head, .edit-crop-window-foot {
            background: var(--ink); color: var(--paper);
            font: 600 9px/1.6 var(--font-mono); text-align: center; pointer-events: none;
        }
        .edit-crop-range { display: flex; flex-direction: column; gap: var(--s-1); }

        /* ---------- Caption live preview ---------- */
        .edit-caption-overlay { position: absolute; left: 4%; right: 4%; text-align: center; pointer-events: none; z-index: 10; }

        #caption-word-group, #caption-word-group-manual {
            background: var(--paper); color: var(--ink); padding: 2px 6px;
        }

        .cw { display: inline-block; color: var(--ink); transform-origin: center bottom; }
        .cw.active { background: var(--ink); color: var(--paper); }

        .anim-karaoke .cw.active { animation: cwKaraoke .15s ease-out forwards; }
        @keyframes cwKaraoke { 0% { transform: scale(1); } 45% { transform: scale(1.18); } 100% { transform: scale(1.06); } }

        .anim-pop .cw { opacity: .5; transform: scale(.9); }
        .anim-pop .cw.active { opacity: 1; animation: cwPop .22s cubic-bezier(.34, 1.56, .64, 1) forwards; }
        @keyframes cwPop { 0% { transform: scale(.6); opacity: 0; } 70% { transform: scale(1.1); opacity: 1; } 100% { transform: scale(1); } }

        .anim-glow .cw { opacity: .5; }
        .anim-glow .cw.active { opacity: 1; animation: cwGlow .4s ease-in-out infinite alternate; }
        @keyframes cwGlow { from { opacity: .55; } to { opacity: 1; } }

        .anim-rise .cw { opacity: .45; }
        .anim-rise .cw.active { opacity: 1; animation: cwRise .22s cubic-bezier(.22, 1, .36, 1) forwards; }
        @keyframes cwRise { 0% { transform: translateY(12px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }

        .anim-block .cw { padding: 0 3px; }
        .anim-block .cw.active { animation: cwBlock .12s ease-out forwards; }
        @keyframes cwBlock { 0% { transform: scale(.9); } 60% { transform: scale(1.05); } 100% { transform: scale(1); } }

        .anim-off .cw { display: none; }
        .anim-off .cw.active { display: inline-block; }

        @media (prefers-reduced-motion: reduce) {
            .anim-karaoke .cw.active, .anim-pop .cw.active, .anim-glow .cw.active,
            .anim-rise .cw.active, .anim-block .cw.active { animation: none; }
            .anim-pop .cw, .anim-rise .cw, .anim-glow .cw { opacity: 1; transform: none; }
        }

        /* ---------- Transport + timeline ---------- */
        .edit-transport { display: flex; flex-wrap: wrap; gap: var(--s-2); margin-top: var(--s-4); }

        .edit-trim-labels { display: flex; justify-content: space-between; align-items: flex-end; gap: var(--s-3); }
        .edit-trim-readout { display: flex; flex-direction: column; gap: 2px; }
        .edit-trim-readout-end { align-items: flex-end; }

        .edit-track {
            position: relative; height: 40px; margin: var(--s-3) 0 var(--s-4);
            background: var(--paper-2); border: 1px solid var(--border-ctl);
            cursor: pointer; user-select: none; touch-action: none;
        }
        .edit-track-selection {
            position: absolute; top: 0; bottom: 0;
            background: transparent; border-left: 2px solid var(--ink); border-right: 2px solid var(--ink);
            pointer-events: none;
        }
        .edit-track-playhead {
            position: absolute; top: -4px; bottom: -4px; width: 2px;
            background: var(--graphite); pointer-events: none; z-index: 5;
        }
        .edit-handle {
            position: absolute; top: 0; bottom: 0; width: 12px;
            transform: translateX(-50%); cursor: ew-resize; z-index: 4;
        }
        .edit-handle-start { background: var(--ink); }
        .edit-handle-end { background: transparent; border: 2px solid var(--ink); }
        @media (pointer: coarse) {
            .edit-handle { width: 24px; }
        }

        /* ---------- Inspector fields ---------- */
        .edit-fields { display: flex; flex-direction: column; gap: var(--s-4); margin-bottom: var(--s-5); }

        /* ---------- Auto Headline live preview ---------- */
        .edit-headline-stage {
            padding: var(--s-4); border: 1.5px dashed var(--border-ctl); border-radius: var(--r);
            background: var(--paper-2); text-align: center;
        }
        .edit-headline-preview {
            display: inline-block; max-width: 100%; padding: 2px var(--s-3);
            font-weight: 700; font-size: 1.25rem; line-height: 1.35;
            overflow-wrap: anywhere;
        }
        .edit-hl-marker { color: var(--card); -webkit-text-stroke: 1.5px var(--ink); text-shadow: 0 1px 0 var(--ink); }
        .edit-hl-highlight { color: var(--ink); background: var(--hl); }
        .edit-hl-sticker { color: var(--card); background: var(--red); border-radius: var(--r); }
        .edit-split-block { display: flex; flex-direction: column; gap: var(--s-2); padding-bottom: var(--s-3); margin-bottom: var(--s-3); border-bottom: 1px solid var(--border-soft); }
        .edit-split-block:last-child { border-bottom: 0; padding-bottom: 0; margin-bottom: 0; }

        /* ---------- Small screens ---------- */
        @media (max-width: 900px) {
            .edit-layout { grid-template-columns: minmax(0, 1fr); }
            .edit-canvas { max-width: 240px; }
        }
        @media (max-width: 480px) {
            .edit-transport .btn, .edit-transport .btn-secondary { flex: 1 1 auto; }
            .edit-seg { flex: 1 1 auto; }
        }
    </style>

    <script>
        if (typeof window.previewInterval === 'undefined') window.previewInterval = null;
        if (typeof window.lastCaptionActiveIdx === 'undefined') window.lastCaptionActiveIdx = -1;
        if (typeof window.cfTimeupdateHandler === 'undefined') window.cfTimeupdateHandler = null;

        window.clipWordsData = @json($clipWords);

        window.addEventListener('words-updated', (event) => {
            if (event.detail && event.detail.words) {
                window.clipWordsData = event.detail.words;
                window.lastCaptionActiveIdx = -1;
            }
        });

        // Color keys map to the monochrome plate tokens; the export applies the real hue.
        window.cfColorMap = { yellow: 'var(--paper)', pink: 'var(--paper)', orange: 'var(--paper)', white: 'var(--paper)' };

        window.getActiveVideo = function() {
            return document.getElementById('editor-video-manual') || document.getElementById('editor-video');
        };

        // Smart DOM diff: only rebuild innerHTML when word GROUP changes;
        // within same group, only swap active class + force reflow to re-trigger animation.
        window.renderCaptionGroup = function(groupWords, activeInGroup, animation, activeColor) {
            ['caption-word-group', 'caption-word-group-manual'].forEach(id => {
                const container = document.getElementById(id);
                if (!container) return;

                container.className = 'anim-' + animation;

                const groupKey = groupWords.map(w => w.word).join('|');

                if (container.dataset.groupKey === groupKey) {
                    container.querySelectorAll('.cw').forEach((s, i) => {
                        const shouldBeActive = (i === activeInGroup);
                        if (shouldBeActive) {
                            s.style.color = activeColor;
                            if (!s.classList.contains('active')) {
                                s.classList.remove('active');
                                void s.offsetWidth; // force reflow to re-trigger CSS @keyframes
                                s.classList.add('active');
                            }
                        } else {
                            s.classList.remove('active');
                            s.style.color = '';
                        }
                    });
                    return;
                }

                container.dataset.groupKey = groupKey;
                container.innerHTML = groupWords.map((w, i) => {
                    const active = (i === activeInGroup);
                    return `<span class="cw${active?' active':''}" style="${active?'color:'+activeColor:''}">${w.word}</span>`;
                }).join(' ');
            });
        };

        // Singleton event listener for live subtitle sync
        window.initLiveSubtitleSync = function() {
            const video = window.getActiveVideo();
            if (!video) return;

            if (window.cfTimeupdateHandler && window.cfTimeupdateVideoRef === video) {
                video.removeEventListener('timeupdate', window.cfTimeupdateHandler);
            }

            window.cfTimeupdateHandler = function() {
                const words = window.clipWordsData;
                if (!words || words.length === 0) return;

                const currentMs = Math.round(video.currentTime * 1000);
                let activeIdx = -1;

                for (let i = 0; i < words.length; i++) {
                    if (currentMs >= words[i].start_ms && currentMs <= words[i].end_ms) {
                        activeIdx = i; break;
                    }
                }
                if (activeIdx === -1) {
                    for (let i = 0; i < words.length; i++) {
                        if (currentMs < words[i].start_ms) { activeIdx = i; break; }
                    }
                }
                if (activeIdx === -1 || activeIdx === window.lastCaptionActiveIdx) return;
                window.lastCaptionActiveIdx = activeIdx;

                const WINDOW = 0;
                const start = Math.max(0, activeIdx - WINDOW);
                const end   = Math.min(words.length - 1, activeIdx + WINDOW);
                const group = words.slice(start, end + 1);
                const activeInGroup = activeIdx - start;

                const container = document.getElementById('caption-word-group') || document.getElementById('caption-word-group-manual');
                const animation  = (container?.dataset.animation) || 'karaoke';
                const colorKey   = (container?.dataset.color) || 'yellow';
                const activeColor = window.cfColorMap[colorKey] || 'var(--paper)';

                window.renderCaptionGroup(group, activeInGroup, animation, activeColor);
            };

            window.cfTimeupdateVideoRef = video;
            video.addEventListener('timeupdate', window.cfTimeupdateHandler);
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', window.initLiveSubtitleSync);
        } else {
            window.initLiveSubtitleSync();
        }

        window.seekEditorVideoTo = window.seekEditorVideoTo || function(ms) {
            if (typeof ms === 'undefined' || ms === null || ms < 0) return;
            const targetSeconds = ms / 1000;
            const video = window.getActiveVideo();
            if (video) {
                try { video.currentTime = targetSeconds; } catch (e) {}
            }
        };

        window.setStartToCurrent = window.setStartToCurrent || function(comp) {
            const video = window.getActiveVideo();
            if (video && comp) {
                const currentMs = Math.round(video.currentTime * 1000);
                window.cfStartMs = currentMs;
                comp.set('editStartMs', currentMs);
                if (window.cfTimelineUpdate) window.cfTimelineUpdate();
            }
        };

        window.setEndToCurrent = window.setEndToCurrent || function(comp) {
            const video = window.getActiveVideo();
            if (video && comp) {
                const currentMs = Math.round(video.currentTime * 1000);
                window.cfEndMs = currentMs;
                comp.set('editEndMs', currentMs);
                if (window.cfTimelineUpdate) window.cfTimelineUpdate();
            }
        };

        // Visual timeline scrubber
        window.initTimelineScrubber = function() {
            const video = window.getActiveVideo();
            const track = document.getElementById('timeline-track');
            if (!video || !track) return;

            const rootElem = track.closest('[wire\\:id]');
            if (rootElem && window.Livewire) {
                window.cfLivewireComp = Livewire.find(rootElem.getAttribute('wire:id'));
            }

            const toMMSS = ms => {
                const s = Math.floor(Math.max(0, ms) / 1000);
                const m = Math.floor(s / 60);
                return `${String(m).padStart(2,'0')}:${String(s % 60).padStart(2,'0')}`;
            };

            const updateUI = () => {
                const durMs  = (video.duration || 0) * 1000;
                const sMs    = Math.max(0, window.cfStartMs || 0);
                const eMs    = Math.min(durMs, window.cfEndMs || durMs);
                const trackW = track.offsetWidth;

                const sPct = durMs > 0 ? sMs / durMs : 0;
                const ePct = durMs > 0 ? eMs / durMs : 1;

                const hS = document.getElementById('tl-handle-start');
                const hE = document.getElementById('tl-handle-end');
                const sel = document.getElementById('tl-selection');
                const lS  = document.getElementById('tl-start-label');
                const lE  = document.getElementById('tl-end-label');
                const dur  = document.getElementById('timeline-duration');

                if (hS) hS.style.left = (sPct * trackW) + 'px';
                if (hE) hE.style.left = (ePct * trackW) + 'px';
                if (sel) {
                    sel.style.left  = (sPct * trackW) + 'px';
                    sel.style.width = ((ePct - sPct) * trackW) + 'px';
                }
                if (lS) lS.textContent = toMMSS(sMs);
                if (lE) lE.textContent = toMMSS(eMs);
                if (dur) {
                    const durSec = Math.round((eMs - sMs) / 1000);
                    const dm = Math.floor(durSec / 60), ds = durSec % 60;
                    dur.textContent = `DUR: ${dm > 0 ? dm + 'm ' : ''}${ds}s`;
                }
            };
            window.cfTimelineUpdate = updateUI;

            // Playhead follows video
            video.addEventListener('timeupdate', () => {
                const ph = document.getElementById('tl-playhead');
                if (!ph || !video.duration) return;
                const pct = video.currentTime / video.duration;
                ph.style.left = (pct * track.offsetWidth) + 'px';
            });

            // Click-to-seek on track
            track.addEventListener('click', e => {
                if (e.target.id === 'tl-handle-start' || e.target.id === 'tl-handle-end') return;
                const rect = track.getBoundingClientRect();
                const pct  = Math.max(0, Math.min(1, (e.clientX - rect.left) / rect.width));
                video.currentTime = pct * (video.duration || 0);
            });

            // Drag factory
            const makeDraggable = (handleId, isStart) => {
                const handle = document.getElementById(handleId);
                if (!handle) return;
                handle.addEventListener('mousedown', e => {
                    e.preventDefault(); e.stopPropagation();
                    const onMove = ev => {
                        const rect = track.getBoundingClientRect();
                        const pct  = Math.max(0, Math.min(1, (ev.clientX - rect.left) / rect.width));
                        const ms   = Math.round(pct * (video.duration || 0) * 1000);
                        const comp = window.cfLivewireComp;
                        if (isStart) {
                            window.cfStartMs = ms;
                            if (comp) comp.set('editStartMs', ms);
                        } else {
                            window.cfEndMs = ms;
                            if (comp) comp.set('editEndMs', ms);
                        }
                        updateUI();
                    };
                    const onUp = () => {
                        document.removeEventListener('mousemove', onMove);
                        document.removeEventListener('mouseup', onUp);
                    };
                    document.addEventListener('mousemove', onMove);
                    document.addEventListener('mouseup', onUp);
                });
            };

            makeDraggable('tl-handle-start', true);
            makeDraggable('tl-handle-end', false);

            // Init on metadata loaded
            const doInit = () => {
                window.cfStartMs = {{ $editStartMs }};
                window.cfEndMs   = {{ $editEndMs }};
                updateUI();
            };
            if (video.readyState >= 1) doInit();
            else video.addEventListener('loadedmetadata', doInit);
        };

        // Boot timeline after subtitle sync
        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', window.initTimelineScrubber);
        } else {
            window.initTimelineScrubber();
        }

        window.jumpToStart = window.jumpToStart || function(startMs) {
            seekEditorVideoTo(startMs);
        };

        window.jumpToEnd = window.jumpToEnd || function(endMs) {
            seekEditorVideoTo(endMs);
        };

        window.playPreview = window.playPreview || function(startMs, endMs) {
            const video = window.getActiveVideo();
            if (video) {
                video.currentTime = (startMs || 0) / 1000;
                video.play();

                if (window.previewInterval) clearInterval(window.previewInterval);

                window.cfTimeupdateHandler = function() {
                const currentMs = Math.round(video.currentTime * 1000);

                if (window.updateLiveAutoPan) {
                    window.updateLiveAutoPan(video);
                }

                if (currentMs < window.cfStartMs || currentMs > window.cfEndMs) {
                    video.style.opacity = '0.35';
                } else {
                    video.style.opacity = '1';
                }    };

                window.previewInterval = setInterval(() => {
                    const currentMs = video.currentTime * 1000;
                    if (currentMs >= endMs) {
                        video.pause();
                        clearInterval(window.previewInterval);
                    }
                }, 50);
            }
        };

        window.updateCropBoxUI = function(normPct) {
            const container = document.getElementById('manual-crop-container');
            const cropBox = document.getElementById('manual-crop-window');
            const label = document.getElementById('manual-crop-x-label');
            const slider = document.getElementById('manual-crop-range');
            if (!container || !cropBox) return;

            const containerW = container.offsetWidth || 560;
            const boxW = cropBox.offsetWidth || 177;
            const maxLeft = containerW - boxW;

            let newLeft = (normPct * containerW) - (boxW / 2);
            newLeft = Math.max(0, Math.min(maxLeft, newLeft));

            cropBox.style.left = newLeft + 'px';

            if (label) label.textContent = (normPct * 100).toFixed(1) + '%';
            if (slider && document.activeElement !== slider) slider.value = normPct;

            if (window.manualCropTimer) clearTimeout(window.manualCropTimer);
            window.manualCropTimer = setTimeout(function() {
                const rootElem = container.closest('[wire\\:id]');
                if (rootElem && window.Livewire) {
                    const compId = rootElem.getAttribute('wire:id');
                    const comp = Livewire.find(compId);
                    if (comp) {
                        comp.set('manualCropX', parseFloat(normPct.toFixed(4)));
                    }
                }
            }, 80);
        };

        // Manual 9:16 crop drag
        window.startManualCropDrag = function(e) {
            const cropBox = document.getElementById('manual-crop-window');
            const container = document.getElementById('manual-crop-container');
            if (!cropBox || !container) return;

            let isDragging = true;
            cropBox.style.cursor = 'grabbing';

            function getEventX(ev) {
                if (ev.touches && ev.touches.length > 0) {
                    return ev.touches[0].clientX;
                }
                return ev.clientX || 0;
            }

            const startX = getEventX(e);
            const containerRect = container.getBoundingClientRect();
            const boxRect = cropBox.getBoundingClientRect();
            const startLeft = boxRect.left - containerRect.left;

            function onMove(ev) {
                if (!isDragging) return;
                const currentX = getEventX(ev);
                const deltaX = currentX - startX;

                let newLeft = startLeft + deltaX;
                newLeft = Math.max(0, Math.min(containerRect.width - boxRect.width, newLeft));

                const centerX = newLeft + (boxRect.width / 2);
                const normPct = Math.max(0, Math.min(1, centerX / containerRect.width));

                window.updateCropBoxUI(normPct);
            }

            function onEnd() {
                isDragging = false;
                cropBox.style.cursor = 'grab';
                window.removeEventListener('mousemove', onMove);
                window.removeEventListener('touchmove', onMove);
                window.removeEventListener('mouseup', onEnd);
                window.removeEventListener('touchend', onEnd);
            }

            window.addEventListener('mousemove', onMove);
            window.addEventListener('touchmove', onMove, { passive: false });
            window.addEventListener('mouseup', onEnd);
            window.addEventListener('touchend', onEnd);

            e.stopPropagation();
            if (e.cancelable) {
                e.preventDefault();
            }
        };

        window.positionManualCropOnClick = function(e) {
            if (e.target.closest('#manual-crop-window') || e.target.closest('input')) return;
            const container = document.getElementById('manual-crop-container');
            if (!container) return;

            const rect = container.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const normPct = Math.max(0, Math.min(1, clickX / rect.width));

            window.updateCropBoxUI(normPct);
        };

        window.activeAutoPanPath = @json($autoPanPath);

        document.addEventListener('auto-pan-updated', (event) => {
            if (event.detail && event.detail.path) {
                window.activeAutoPanPath = event.detail.path;
            }
        });

        window.updateLiveAutoPan = function(video) {
            if (!video || !window.activeAutoPanPath || window.activeAutoPanPath.length === 0) return;
            const path = window.activeAutoPanPath;

            const rootElem = video.closest('[wire\\:id]');
            let startMs = 0;
            if (rootElem && window.Livewire) {
                const comp = Livewire.find(rootElem.getAttribute('wire:id'));
                if (comp) startMs = comp.get('editStartMs') || 0;
            }

            const currentSec = Math.max(0, video.currentTime - (startMs / 1000.0));
            let targetX = path[0].x;

            for (let i = 0; i < path.length - 1; i++) {
                const p1 = path[i];
                const p2 = path[i + 1];
                if (currentSec >= p1.t && currentSec <= p2.t) {
                    const ratio = (p2.t > p1.t) ? (currentSec - p1.t) / (p2.t - p1.t) : 0;
                    targetX = p1.x + (p2.x - p1.x) * ratio;
                    break;
                } else if (currentSec > p2.t) {
                    targetX = p2.x;
                }
            }

            const videoElem = document.getElementById('editor-video');
            if (videoElem) {
                videoElem.style.objectPosition = (targetX * 100).toFixed(1) + '% center';
            }
        };

        window.splitAtCurrentPlayhead = function() {
            const video = window.getActiveVideo();
            if (!video) return;
            const currentMs = Math.round(video.currentTime * 1000);
            const rootElem = video.closest('[wire\\:id]') || document.querySelector('[wire\\:id]');
            if (rootElem && window.Livewire) {
                const comp = Livewire.find(rootElem.getAttribute('wire:id'));
                if (comp) {
                    comp.call('splitSegmentAtCurrentTime', currentMs);
                }
            }
        };

        const bootEditorEngines = function() {
            window.initLiveSubtitleSync();
            window.initTimelineScrubber();
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', bootEditorEngines);
        } else {
            bootEditorEngines();
        }

        document.addEventListener('livewire:initialized', () => {
            Livewire.hook('morph.updated', () => {
                bootEditorEngines();
            });
        });
    </script>
</div>
