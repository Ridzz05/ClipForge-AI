<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb" style="font: 11px/1 var(--mono); text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
        <a href="/" wire:navigate style="color: var(--muted); display: inline-flex; align-items: center; gap: 4px;"><i class="ph ph-house" style="font-size: 13px;"></i> Dashboard</a>
        <span style="color: var(--line);">/</span>
        <span style="color: var(--accent); font-weight: 600;">Review Video #{{ $video->id }}</span>
    </nav>

    <!-- Page Header & Navigation -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Review &amp; Edit Kandidat</h1>
            <p class="page-sub" style="margin-bottom:0; display: flex; align-items: center; gap: 8px;">
                <span class="muted" style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $video->source_ref ?? '—' }}</span>
                @if($video->duration_seconds)
                    <span style="color: var(--line);">|</span>
                    <span class="muted font-semibold">{{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}</span>
                @endif
                <span style="color: var(--line);">|</span>
                <span style="color: var(--accent); font-weight: 600;">{{ $candidates->count() }} Kandidat Terdeteksi</span>
            </p>
        </div>
        <div class="row" style="gap: 12px;">
            <button type="button" class="btn btn-sm btn-outline" wire:click="createCustomCandidate" style="color: var(--text-title); border-color: var(--border-stage); background: rgba(255,255,255,0.03);">
                <i class="ph ph-plus-circle" style="font-size: 14px; vertical-align: middle;"></i> Buat Klip Kustom
            </button>
            @if($poll)
                <span class="badge badge-amber" style="background: var(--tile-1); color: var(--ink); border: 1px solid rgba(0,0,0,0.06);">
                    <i class="ph ph-spinner-gap spin-rotate" style="font-size: 14px;"></i>&nbsp;Mengekspor &amp; Merender
                </span>
            @endif
        </div>
    </div>



    <!-- Split Workspace -->
    <div class="grid review-split" style="grid-template-columns: 420px 1fr; gap: 28px; align-items: start;">
        
        <!-- Left Side: Sticky Workspace Panel -->
        <div style="position: sticky; top: 92px;" class="grid">
            @if($editingId === null)
                <!-- View Mode: Sticky Media Player -->
                <div class="panel" style="padding: 20px;">
                    <h4 style="margin: 0 0 12px; font-family: var(--mono); font-size: 11px; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; color: var(--muted);">Video Sumber Asli</h4>
                    <div style="border-radius: 14px; overflow: hidden; background: #000; box-shadow: 0 8px 24px rgba(0,0,0,0.15); border: 1.5px solid var(--line);">
                        <video controls id="editor-video" preload="metadata" style="width:100%; display: block; aspect-ratio: 16/9;" src="/videos/{{ $video->id }}/source">
                            Browser Anda tidak mendukung HTML5 video.
                        </video>
                    </div>
                    <p class="muted" style="font-size: 12px; line-height: 1.5; margin: 12px 0 0; color: var(--muted); font-weight: 500;">
                        Gunakan pemutar di atas untuk meninjau bagian stempel waktu di kanan. Untuk mengedit stempel waktu klip secara akurat, klik tombol **Edit** pada kartu klip yang bersangkutan.
                    </p>
                    <div style="margin-top: 14px;">
                        <a href="/openreel/?video_url={{ urlencode('/videos/' . $video->id . '/source') }}" target="_blank" class="btn btn-sm btn-outline" style="border-color: #3b82f6; color: #3b82f6; display: flex; align-items: center; justify-content: center; gap: 6px; padding: 10px 14px; border-radius: 10px; font-weight: 700; text-decoration: none;">
                            <i class="ph ph-video" style="font-size: 16px;"></i> Edit Video di OpenReel
                        </a>
                    </div>
                </div>

                <!-- STYLE & CAPTIONS MOOD PANEL (Ref: clip_caption.png) -->
                <div class="panel" style="padding: 20px; background: var(--bg-surface); border-radius: 18px; border: 1px solid var(--border-color);">
                    <div class="row between" style="margin-bottom: 16px;">
                        <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); letter-spacing: 0.1em; color: var(--text-muted);">STYLE & CAPTIONS</span>
                        <span class="badge badge-purple" style="font-size: 10px; text-transform: uppercase;">CAPTION MOOD</span>
                    </div>

                    <!-- BURN SUBTITLES INTO CLIP -->
                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">BURN SUBTITLES INTO CLIP</label>
                        <div style="display: flex; background: var(--bg-surface-subtle); border-radius: 99px; padding: 4px; border: 1px solid var(--border-color);">
                            <button type="button" wire:click="$set('burnSubtitles', 'on')" style="flex: 1; padding: 7px; border-radius: 99px; border: none; font-weight: 700; font-size: 11px; cursor: pointer; transition: all 0.2s;" class="{{ $burnSubtitles === 'on' ? 'btn' : 'btn-outline' }}">ON</button>
                            <button type="button" wire:click="$set('burnSubtitles', 'off')" style="flex: 1; padding: 7px; border-radius: 99px; border: none; font-weight: 700; font-size: 11px; cursor: pointer; transition: all 0.2s;" class="{{ $burnSubtitles === 'off' ? 'btn' : 'btn-outline' }}">OFF</button>
                        </div>
                    </div>

                    <!-- SUBTITLE STYLE COLOR -->
                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">SUBTITLE COLOR PRESET</label>
                        <div style="display: flex; gap: 10px; align-items: center;">
                            <button type="button" wire:click="$set('subtitleColor', 'yellow')" style="width: 26px; height: 26px; border-radius: 50%; background: #ffff00; border: 2.5px solid {{ $subtitleColor === 'yellow' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;" title="Yellow Neon"></button>
                            <button type="button" wire:click="$set('subtitleColor', 'pink')" style="width: 26px; height: 26px; border-radius: 50%; background: #ff4d6d; border: 2.5px solid {{ $subtitleColor === 'pink' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;" title="Hot Pink"></button>
                            <button type="button" wire:click="$set('subtitleColor', 'orange')" style="width: 26px; height: 26px; border-radius: 50%; background: #ff8c32; border: 2.5px solid {{ $subtitleColor === 'orange' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;" title="Vibrant Orange"></button>
                            <button type="button" wire:click="$set('subtitleColor', 'white')" style="width: 26px; height: 26px; border-radius: 50%; background: #ffffff; border: 2.5px solid {{ $subtitleColor === 'white' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;" title="Pure White"></button>
                        </div>
                    </div>

                    <!-- ANIMATION WORD-BY-WORD -->
                    <div style="margin-bottom: 18px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">ANIMATION · WORD-BY-WORD "TYPE"</label>
                        <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;">
                            @foreach(['POP', 'FADE', 'RISE', 'DROP', 'ZOOM', 'BLUR', 'TYPE', 'OFF'] as $anim)
                                <button type="button" wire:click="$set('captionAnimation', '{{ strtolower($anim) }}')" 
                                        style="padding: 6px 2px; font-size: 10px; font-weight: 800; border-radius: 8px; cursor: pointer; transition: all 0.2s;" 
                                        class="{{ $captionAnimation === strtolower($anim) ? 'btn' : 'btn-outline' }}">
                                    {{ $anim }}
                                </button>
                            @endforeach
                        </div>
                    </div>

                    <!-- SLIDERS: FONT SIZE & Y POSITION -->
                    <div style="display: flex; flex-direction: column; gap: 12px; margin-bottom: 20px;">
                        <div>
                            <div class="row between" style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
                                <span>FONT SIZE</span>
                                <span>{{ $captionFontSize }}</span>
                            </div>
                            <input type="range" min="40" max="120" wire:model.live="captionFontSize" style="width:100%; accent-color: var(--purple-primary);">
                        </div>
                        <div>
                            <div class="row between" style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
                                <span>CAPTION Y POSITION</span>
                                <span>{{ $captionPosY }}</span>
                            </div>
                            <input type="range" min="200" max="1800" wire:model.live="captionPosY" style="width:100%; accent-color: var(--purple-primary);">
                        </div>
                    </div>

                    <!-- BATCH RENDER BUTTON -->
                    <button type="button" wire:click="batchRenderAll" class="btn" style="width: 100%; padding: 12px; font-size: 13px; font-weight: 800; background: #ffff00; color: #000000; box-shadow: 0 4px 16px rgba(255, 255, 0, 0.4); border-radius: 99px;">
                        ⚡ Render All Clips (new files)
                    </button>
                </div>

            @else
                <!-- Edit Mode: Sticky Clip Editor Workspace -->
                <div class="panel grid" style="gap: 16px; padding: 20px; background: var(--tile-3); border-color: rgba(0,0,0,0.05); color: var(--ink);">
                    <h4 style="margin: 0; font-family: var(--serif); font-style: italic; font-size: 24px; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 6px;">
                        <i class="ph ph-scissors" style="font-size: 22px; color: var(--ink);"></i>
                        {{ $editingId === -1 ? 'Buat Klip Kustom' : 'Edit Klip #' . $editingId }}
                    </h4>
                    
                    <div style="border-radius: 14px; overflow: hidden; background: #000; border: 1.5px solid var(--ink); position: relative;">
                        <video id="editor-video" controls preload="metadata" 
                               onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                               style="width:100%; display: block; aspect-ratio: 16/9;" 
                               src="/videos/{{ $video->id }}/source">
                        </video>
                    </div>


                    <!-- Quick In/Out Buttons -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="setStartToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu awal">
                            <i class="ph ph-map-pin-line" style="font-size: 12px; vertical-align: middle;"></i> Set Awal
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setEndToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu akhir">
                            <i class="ph ph-flag-banner" style="font-size: 12px; vertical-align: middle;"></i> Set Akhir
                        </button>
                    </div>

                    <!-- Seek & Preview Actions -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm btn-primary" onclick="playPreview()" style="flex: 2; padding: 8px 12px; font-size: 11px; border-color: var(--ink); background: var(--ink); color: var(--paper);">
                            <i class="ph ph-play-circle" style="font-size: 13px; vertical-align: middle;"></i> Preview Klip
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="jumpToStart()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker awal">
                            <i class="ph ph-caret-double-left" style="font-size: 12px; vertical-align: middle;"></i> Awal
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="jumpToEnd()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker akhir">
                            <i class="ph ph-caret-double-right" style="font-size: 12px; vertical-align: middle;"></i> Akhir
                        </button>
                    </div>

                    <!-- Start / End Millisecond Fields -->
                    <div class="row" style="gap: 12px;">
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase; color: var(--ink);">Start (ms)</label>
                            <input type="number" wire:model.live="editStartMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: var(--paper); border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px;">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase; color: var(--ink);">End (ms)</label>
                            <input type="number" wire:model.live="editEndMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: var(--paper); border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px;">
                        </div>
                    </div>

                    <!-- Hook Score slider -->
                    <div>
                        <div class="row between" style="margin-bottom: 4px;">
                            <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; text-transform: uppercase; color: var(--ink);">Hook Score</label>
                            <span style="font-weight: 800; color: var(--ink); font-size: 13px;">{{ $editHookScore }}%</span>
                        </div>
                        <input type="range" min="0" max="100" wire:model="editHookScore" style="width:100%; accent-color: var(--ink); cursor: pointer;">
                    </div>

                    <!-- Rationale textarea -->
                    <div>
                        <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase; color: var(--ink);">Deskripsi / Rationale</label>
                        <textarea wire:model="editRationale" rows="3" style="width:100%; padding: 8px 12px; border-radius: 8px; background: var(--paper); border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px; resize: vertical; line-height: 1.4;"></textarea>
                    </div>

                    <!-- Save / Cancel Buttons -->
                    <div class="row" style="gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn btn-sm btn-outline" wire:click="closeEditor" style="flex: 1; border-color: var(--ink); color: var(--ink);">
                            Batal
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="{{ $editingId === -1 ? 'saveCustomCandidate' : 'saveCandidate' }}" style="flex: 1; border-color: var(--ink); background: var(--ink); color: var(--paper);">
                            Simpan
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Side: Configuration & Candidates -->
        <div class="grid" style="gap: 20px;">
            
            <!-- CTA & Caption Configurator -->
            <div class="panel grid" style="gap: 20px; background: var(--tile-6); border-color: rgba(0,0,0,0.05); color: var(--ink);">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-family: var(--serif); font-style: italic; font-weight: 700; font-size: 22px; color: var(--ink);">Pengaturan Ekspor &amp; Subtitle</label>
                    <span style="font-size: 12px; color: var(--muted); font-weight: 500;">Konfigurasi teks CTA dan posisi teks subtitle otomatis sebelum melakukan approval.</span>
                </div>
                
                <!-- CTA text section -->
                <div class="grid" style="gap: 8px;">
                    <label style="font-weight: 700; font-size: 13px; color: var(--ink);">Teks Call-to-Action (CTA)</label>
                    <input type="text" wire:model="ctaText" maxlength="120"
                           placeholder="Masukkan CTA khusus (cth. BELI SEKARANG DI STEAM)"
                           style="width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--paper);
                                  border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;">
                    
                    <div class="grid" style="gap: 6px;">
                        <span style="font-size: 11px; font-family: var(--mono); font-weight: 700; text-transform: uppercase; color: var(--ink);">Pilihan Preset Cepat</span>
                        <div class="row" style="gap: 6px; flex-wrap: wrap;">
                            @foreach($this->ctaPresets() as $preset)
                                <button type="button" class="btn btn-sm btn-outline"
                                        wire:click="$set('ctaText', @js($preset))"
                                        style="font-size: 10.5px; padding: 6px 12px; background: rgba(255,255,255,0.3); font-weight: 600; border-radius: 8px; border-color: rgba(0,0,0,0.12); color: var(--ink);">
                                    {{ $preset }}
                                </button>
                            @endforeach
                        </div>
                    </div>
                    
                    @if(trim($ctaText) === '')
                        <div style="font-size: 11.5px; color: #b45309; display: flex; gap: 6px; align-items: center; font-weight: 700;">
                            <i class="ph ph-warning" style="font-size: 14px; vertical-align: middle;"></i>
                            Teks CTA kosong. Direkomendasikan mengisi CTA.
                        </div>
                    @endif
                </div>

                <hr style="border: none; border-top: 1.5px dashed rgba(26,23,20,0.1); margin: 4px 0;">

                <!-- Subtitle Style section -->
                <div class="grid" style="gap: 12px; margin-bottom: 12px;">
                    <div>
                        <label style="font-weight: 700; font-size: 13px; color: var(--ink); display: block; margin-bottom: 6px;">Layout Video</label>
                        <select wire:model="layout"
                                style="width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--paper);
                                       border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;
                                       outline: none; transition: border-color 0.2s ease; cursor: pointer;">
                            <option value="single">Single Speaker Crop (9:16 Face Tracking)</option>
                            <option value="split_gaming">Gaming Split-Screen (Facecam Atas + Gameplay Bawah)</option>
                        </select>
                    </div>
                </div>

                <div class="grid" style="gap: 12px;">
                    <div>
                        <label style="font-weight: 700; font-size: 13px; color: var(--ink); display: block; margin-bottom: 6px;">Gaya Auto-Caption</label>
                        <select wire:model="captionStyle"
                                style="width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--paper);
                                       border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;
                                       outline: none; transition: border-color 0.2s ease; cursor: pointer;">
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

                    <!-- Subtitle Position slider -->
                    <div>
                        <div class="row between" style="margin-bottom: 6px;">
                            <label style="font-weight: 700; font-size: 13px; color: var(--ink);">Posisi Vertikal Caption</label>
                            <span style="font-weight: 800; color: var(--ink); font-size: 13px; font-family: var(--mono);">{{ $captionMarginV }} px dari bawah</span>
                        </div>
                        <input type="range" min="40" max="1200" step="10" wire:model.live="captionMarginV"
                               style="width:100%; accent-color: var(--ink); cursor: pointer;">
                        <span style="font-size: 11px; color: var(--muted); font-weight: 500; display: block; margin-top: 4px;">
                            Geser slider ke kanan untuk memindahkan posisi teks ke arah tengah/atas layar (1920px tinggi total).
                        </span>
                    </div>
                </div>
            </div>

            <!-- FORMAT & ORIENTATION SELECTOR (Ref: orient.webp) -->
            <div class="panel" style="padding: 16px 20px; background: var(--bg-surface); border-radius: 16px; border: 1px solid var(--border-color); margin-bottom: 16px;">
                <div class="row between" style="margin-bottom: 12px;">
                    <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); letter-spacing: 0.1em; color: var(--text-muted);">RENDER FORMAT & ORIENTATION</span>
                    <span class="badge badge-purple" style="font-size: 10px;">HOOK DETECTION MODE</span>
                </div>
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 8px;">
                    <button type="button" wire:click="$set('renderFormat', 'face_916')" class="{{ $renderFormat === 'face_916' ? 'btn' : 'btn-outline' }}" style="padding: 9px 12px; font-size: 11.5px; border-radius: 8px; font-weight: 700;">
                        <i class="ph ph-user-focus" style="font-size: 14px; vertical-align: middle;"></i> Face 9:16
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'blur_916')" class="{{ $renderFormat === 'blur_916' ? 'btn' : 'btn-outline' }}" style="padding: 9px 12px; font-size: 11.5px; border-radius: 8px; font-weight: 700;">
                        <i class="ph ph-selection-background" style="font-size: 14px; vertical-align: middle;"></i> Blur BG 9:16
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'square_11')" class="{{ $renderFormat === 'square_11' ? 'btn' : 'btn-outline' }}" style="padding: 9px 12px; font-size: 11.5px; border-radius: 8px; font-weight: 700;">
                        <i class="ph ph-square" style="font-size: 14px; vertical-align: middle;"></i> Square 1:1
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'landscape_169')" class="{{ $renderFormat === 'landscape_169' ? 'btn' : 'btn-outline' }}" style="padding: 9px 12px; font-size: 11.5px; border-radius: 8px; font-weight: 700;">
                        <i class="ph ph-rectangle" style="font-size: 14px; vertical-align: middle;"></i> Landscape 16:9
                    </button>
                </div>
            </div>

            <!-- Candidate Clip Cards -->
            <div class="grid" style="gap: 14px;">

                @forelse($candidates as $c)
                    @php
                        $dur = ($c->end_ms - $c->start_ms) / 1000;
                        [$sCls,$sLabel] = match($c->status) {
                            'approved' => ['badge-amber','Disetujui &bull; Rendering'],
                            'exported' => ['badge-green','Selesai Diekspor'],
                            'rejected' => ['badge-gray','Ditolak'],
                            default => ['badge-blue','Menunggu Tinjauan'],
                        };
                        $fmt = fn($ms) => gmdate('i:s', intdiv($ms,1000));
                        
                        // Score-based border color & glowing effect
                        $scoreGlow = $c->hook_score >= 75 
                            ? 'border-left: 5px solid #16a34a; box-shadow: 0 4px 20px rgba(0,0,0,0.02);' 
                            : ($c->hook_score >= 50 
                                ? 'border-left: 5px solid #d97706; box-shadow: 0 4px 20px rgba(0,0,0,0.02);' 
                                : 'border-left: 5px solid var(--muted);');
                    @endphp
                    <div class="panel" wire:key="cand-{{ $c->id }}" style="{{ $scoreGlow }}">
                        <div class="row between" style="align-items: center; gap: 20px;">
                            
                            <!-- Score & Time Frame info -->
                            <div class="row" style="gap: 20px; align-items: center; flex: 1;">
                                <!-- Glowing Score Badge -->
                                <div style="text-align: center; min-width: 64px; background: rgba(0,0,0,0.02); padding: 10px; border-radius: 12px; border: 1.5px solid var(--line);">
                                    <div style="font-family: var(--mono); font-size: 24px; font-weight: 800; line-height: 1;
                                         color: {{ $c->hook_score >= 75 ? '#16a34a' : ($c->hook_score >= 50 ? '#d97706' : 'var(--muted)') }};">
                                        {{ $c->hook_score }}
                                    </div>
                                    <div class="muted" style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Score</div>
                                </div>
                                
                                <div>
                                    <div style="font-size: 15px; font-weight: 700; color: var(--ink);">
                                        {{ $fmt($c->start_ms) }} &rarr; {{ $fmt($c->end_ms) }}
                                        <span class="muted" style="font-weight: 500; font-size: 13px; margin-left: 6px;">({{ number_format($dur, 1) }} Detik)</span>
                                    </div>
                                    <div style="margin-top: 6px;">
                                        <span class="badge {{ $sCls }}">{{ $sLabel }}</span>
                                    </div>
                                </div>
                            </div>

                            <!-- Actions -->
                            <div class="row" style="gap: 8px;">
                                @if(in_array($c->status, ['pending','rejected']))
                                    <!-- Dedicated Live Studio Editor Link -->
                                    <a href="/candidates/{{ $c->id }}/edit" wire:navigate class="btn btn-sm btn-outline" title="Buka Live Studio Editor untuk klip ini">
                                        <i class="ph ph-sliders" style="font-size:12px; vertical-align: middle;"></i> Live Edit
                                    </a>
                                    <button class="btn btn-sm btn-primary" style="border-color: #16a34a; background: #16a34a; color: #ffffff;"

                                            wire:click="approve({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="approve({{ $c->id }})">
                                        <i class="ph ph-sparkle" style="font-size:12px; vertical-align: middle;"></i> Approve
                                    </button>
                                @endif
                                @if(in_array($c->status, ['pending','approved']))
                                    <button class="btn btn-sm btn-outline" style="border-color: #dc2626; color: #dc2626;"
                                            wire:click="reject({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="reject({{ $c->id }})">
                                        <i class="ph ph-x-circle" style="font-size:12px; vertical-align: middle;"></i> Reject
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- LLM Rationale text -->
                        @if($c->score_rationale)
                            <div style="margin-top: 14px; font-size: 13px; border-top: 1px solid var(--line); padding-top: 12px; line-height: 1.6; color: var(--muted); display: flex; gap: 8px; align-items: flex-start;">
                                <i class="ph ph-chat-centered-dots" style="font-size:15px; flex-shrink: 0; color: var(--ink); margin-top: 3px;"></i>
                                <p style="margin: 0; color: var(--muted); font-weight: 500;">{{ $c->score_rationale }}</p>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="panel empty">
                        Belum ada kandidat klip yang dihasilkan. Jika pipeline transkripsi/scoring masih berjalan di latar belakang, daftar klip akan otomatis dimuat di sini secara berkala.
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
        }

        let previewInterval = null;

        window.setStartToCurrent = () => {
            const video = document.getElementById('editor-video');
            if (video) {
                const currentMs = Math.round(video.currentTime * 1000);
                Livewire.dispatch('set-start-ms', { ms: currentMs });
                @this.set('editStartMs', currentMs);
            }
        };

        window.setEndToCurrent = () => {
            const video = document.getElementById('editor-video');
            if (video) {
                const currentMs = Math.round(video.currentTime * 1000);
                @this.set('editEndMs', currentMs);
            }
        };

        window.jumpToStart = () => {
            const startMs = @this.get('editStartMs');
            seekEditorVideoTo(startMs);
        };

        window.jumpToEnd = () => {
            const endMs = @this.get('editEndMs');
            seekEditorVideoTo(endMs);
        };

        window.playPreview = () => {
            const video = document.getElementById('editor-video');
            if (video) {
                const startMs = @this.get('editStartMs');
                const endMs = @this.get('editEndMs');
                video.currentTime = startMs / 1000;
                video.play();

                if (previewInterval) clearInterval(previewInterval);

                previewInterval = setInterval(() => {
                    const currentMs = video.currentTime * 1000;
                    if (currentMs >= endMs) {
                        video.pause();
                        clearInterval(previewInterval);
                    }
                }, 50);
            }
        };
    </script>

</div>
