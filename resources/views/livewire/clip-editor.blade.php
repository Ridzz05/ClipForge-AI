<div class="grid" style="gap: 24px;">
    <!-- Breadcrumb -->
    <nav class="breadcrumb" style="font: 11px/1 var(--font-mono); text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 8px; display: flex; align-items: center; gap: 6px;">
        <a href="/" wire:navigate style="color: var(--text-muted);"><i class="ph ph-house"></i> Dashboard</a>
        <span style="color: var(--border-color);">/</span>
        <a href="/videos/{{ $candidate->video_id }}/review" wire:navigate style="color: var(--text-muted);">Review Video #{{ $candidate->video_id }}</a>
        <span style="color: var(--border-color);">/</span>
        <span style="color: var(--purple-primary); font-weight: 700;">Live Studio Editor Klip #{{ $candidate->id }}</span>
    </nav>

    <!-- Header -->
    <div class="row between" style="align-items: center;">
        <div>
            <h1 class="page-title">Live Studio Editor — Klip #{{ $candidate->id }}</h1>
            <p class="page-sub" style="margin: 0;">Pratinjau langsung 9:16 vertikal, animasi caption per-kata, dan kustomisasi framing sebelum diekspor.</p>
        </div>
        <div class="row" style="gap: 10px;">
            <a href="/videos/{{ $candidate->video_id }}/review" wire:navigate class="btn btn-outline">
                <i class="ph ph-arrow-left"></i> Kembali ke Daftar
            </a>
            <button type="button" wire:click="saveAndApprove" class="btn" style="background: var(--purple-gradient); box-shadow: 0 4px 16px rgba(154, 85, 255, 0.4);">
                ⚡ Export &amp; Render Klip Ini
            </button>
        </div>
    </div>

    <!-- Main Live Editor Grid (2 Columns: Studio Live Canvas vs Control Inspector) -->
    <div class="grid" style="grid-template-columns: minmax(0, 1fr) 360px; gap: 24px; align-items: start;">
        
        <!-- Left Column: Live 9:16 & 16:9 Preview Canvas -->
        <div class="grid" style="gap: 20px;">
            <!-- 9:16 Vertical Live Preview Box (Ref: orient.webp & clip_caption.png) -->
            <div class="panel" style="padding: 24px; background: var(--bg-surface); text-align: center;">

                <div class="row between" style="width: 100%; margin-bottom: 16px;">
                    <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted); text-transform: uppercase;">LIVE 9:16 CANVAS PREVIEW</span>
                    <span class="badge badge-purple" style="padding: 4px 10px;">{{ strtoupper($renderFormat) }}</span>
                </div>

                <!-- 9:16 Simulated Mobile Player Screen -->
                <div style="display: flex; justify-content: center; width: 100%; margin-bottom: 20px;">
                    <div id="live-canvas-frame" style="width: 250px; height: 444px; background: #000; border-radius: 20px; overflow: hidden; position: relative; box-shadow: 0 12px 36px rgba(0,0,0,0.3); border: 2px solid var(--border-color);">
                        <video id="editor-video" controls preload="metadata" 
                               onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                               style="width: 100%; height: 100%; object-fit: cover; display: block;" 
                               src="/videos/{{ $candidate->video_id }}/source">
                        </video>

                        <!-- Real-Time Subtitle Overlay Preview (Opus Clip Style) -->
                        @if($burnSubtitles === 'on')
                            <div id="subtitle-overlay" style="position: absolute; bottom: {{ ($captionPosY / 1920) * 100 }}%; left: 4%; right: 4%; text-align: center; pointer-events: none; z-index: 10;">
                                <div id="caption-word-group"
                                    data-animation="{{ $captionAnimation }}"
                                    data-color="{{ $subtitleColor }}"
                                    data-fontsize="{{ ($captionFontSize / 1920) * 450 }}"
                                    style="
                                        font-family: 'Outfit', sans-serif;
                                        font-size: {{ ($captionFontSize / 1920) * 450 }}px;
                                        font-weight: 900;
                                        text-transform: uppercase;
                                        letter-spacing: 0.03em;
                                        line-height: 1.3;
                                        display: flex;
                                        flex-wrap: wrap;
                                        justify-content: center;
                                        gap: 0 5px;
                                        min-height: 1.5em;
                                    ">
                                    <!-- JS renders word spans here -->
                                </div>
                            </div>
                        @endif
                    </div>

                </div>

                <!-- Player Action Buttons -->
                <div style="display: flex; gap: 8px; justify-content: center; width: 100%;">
                    <button type="button" class="btn btn-sm btn-primary" onclick="playPreview(Livewire.find('{{ $this->getId() }}').get('editStartMs'), Livewire.find('{{ $this->getId() }}').get('editEndMs'))" style="flex: 2; padding: 10px; font-weight: 800; background: var(--purple-gradient);">
                        <i class="ph ph-play-circle" style="font-size: 16px;"></i> Play Loop Preview
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="jumpToStart(Livewire.find('{{ $this->getId() }}').get('editStartMs'))" style="flex: 1; font-weight: 700;">
                        <i class="ph ph-caret-double-left"></i> Awal
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="jumpToEnd(Livewire.find('{{ $this->getId() }}').get('editEndMs'))" style="flex: 1; font-weight: 700;">
                        <i class="ph ph-caret-double-right"></i> Akhir
                    </button>
                </div>
            </div>



            <!-- Timestamp In/Out Precision Selector -->
            <div class="panel" style="padding: 20px; background: var(--bg-surface);">
                <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted); display: block; margin-bottom: 14px;">TIMESTAMP MARKERS (MS)</span>
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 16px; margin-bottom: 14px;">
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">START (MS)</label>
                        <input type="number" wire:model.live.debounce.300ms="editStartMs">
                    </div>
                    <div>
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 4px;">END (MS)</label>
                        <input type="number" wire:model.live.debounce.300ms="editEndMs">
                    </div>
                </div>
                <div class="row" style="gap: 10px;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="setStartToCurrent(Livewire.find('{{ $this->getId() }}'))" style="flex: 1;">
                        <i class="ph ph-map-pin-line"></i> Set Start to Current
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setEndToCurrent(Livewire.find('{{ $this->getId() }}'))" style="flex: 1;">
                        <i class="ph ph-flag-banner"></i> Set End to Current
                    </button>
                </div>
            </div>
        </div>

        <!-- Right Column: Inspector Controls (Caption Mood & Render Format) -->
        <div class="grid" style="gap: 20px;">
            <!-- FORMAT & ORIENTATION SELECTOR (Ref: orient.webp) -->
            <div class="panel" style="padding: 20px; background: var(--bg-surface);">
                <div class="row between" style="margin-bottom: 14px;">
                    <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted);">RENDER FORMAT &amp; ORIENTATION</span>
                    <span class="badge badge-purple" style="font-size: 10px;">LAYOUT</span>
                </div>
                <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 8px;">
                    <button type="button" wire:click="$set('renderFormat', 'face_916')" class="{{ $renderFormat === 'face_916' ? 'btn' : 'btn-outline' }}" style="padding: 10px 8px; font-size: 11px;">
                        <i class="ph ph-user-focus"></i> Face 9:16
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'blur_916')" class="{{ $renderFormat === 'blur_916' ? 'btn' : 'btn-outline' }}" style="padding: 10px 8px; font-size: 11px;">
                        <i class="ph ph-selection-background"></i> Blur BG 9:16
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'square_11')" class="{{ $renderFormat === 'square_11' ? 'btn' : 'btn-outline' }}" style="padding: 10px 8px; font-size: 11px;">
                        <i class="ph ph-square"></i> Square 1:1
                    </button>
                    <button type="button" wire:click="$set('renderFormat', 'landscape_169')" class="{{ $renderFormat === 'landscape_169' ? 'btn' : 'btn-outline' }}" style="padding: 10px 8px; font-size: 11px;">
                        <i class="ph ph-rectangle"></i> Landscape 16:9
                    </button>
                </div>
            </div>

            <!-- STYLE & CAPTIONS MOOD PANEL (Ref: clip_caption.png) -->
            <div class="panel" style="padding: 20px; background: var(--bg-surface);">
                <div class="row between" style="margin-bottom: 16px;">
                    <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted);">STYLE &amp; CAPTIONS</span>
                    <span class="badge badge-purple" style="font-size: 10px;">CAPTION MOOD</span>
                </div>

                <!-- LIVE TRANSLATION SELECTOR -->
                <div style="margin-bottom: 18px;">
                    <div class="row between" style="margin-bottom: 6px;">
                        <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin: 0;">TARGET TRANSLATION LANGUAGE</label>
                        <span wire:loading wire:target="updatedTargetLanguage" class="badge badge-amber" style="font-size: 9px;">
                            <i class="ph ph-spinner-gap spin-rotate"></i> Translating...
                        </span>
                    </div>
                    <select wire:model.live="targetLanguage" style="width: 100%; padding: 8px 12px; border-radius: 8px; font-size: 11px; background: var(--bg-surface-subtle); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer; font-weight: 700;">
                        @foreach(\App\Services\TranslationService::LANGUAGES as $code => $name)
                            <option value="{{ $code }}">{{ $name }}</option>
                        @endforeach
                    </select>
                </div>

                <!-- BURN SUBTITLES TOGGLE -->
                <div style="margin-bottom: 18px;">
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">BURN SUBTITLES INTO CLIP</label>
                    <div style="display: flex; background: var(--bg-surface-subtle); border-radius: 99px; padding: 4px; border: 1px solid var(--border-color);">
                        <button type="button" wire:click="$set('burnSubtitles', 'on')" style="flex: 1; padding: 7px; border-radius: 99px; border: none; font-weight: 700; font-size: 11px; cursor: pointer;" class="{{ $burnSubtitles === 'on' ? 'btn' : 'btn-outline' }}">ON</button>
                        <button type="button" wire:click="$set('burnSubtitles', 'off')" style="flex: 1; padding: 7px; border-radius: 99px; border: none; font-weight: 700; font-size: 11px; cursor: pointer;" class="{{ $burnSubtitles === 'off' ? 'btn' : 'btn-outline' }}">OFF</button>
                    </div>
                </div>


                <!-- COLOR PRESET -->
                <div style="margin-bottom: 18px;">
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">SUBTITLE COLOR PRESET</label>
                    <div style="display: flex; gap: 10px; align-items: center;">
                        <button type="button" wire:click="$set('subtitleColor', 'yellow')" style="width: 28px; height: 28px; border-radius: 50%; background: #ffff00; border: 3px solid {{ $subtitleColor === 'yellow' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;"></button>
                        <button type="button" wire:click="$set('subtitleColor', 'pink')" style="width: 28px; height: 28px; border-radius: 50%; background: #ff4d6d; border: 3px solid {{ $subtitleColor === 'pink' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;"></button>
                        <button type="button" wire:click="$set('subtitleColor', 'orange')" style="width: 28px; height: 28px; border-radius: 50%; background: #ff8c32; border: 3px solid {{ $subtitleColor === 'orange' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;"></button>
                        <button type="button" wire:click="$set('subtitleColor', 'white')" style="width: 28px; height: 28px; border-radius: 50%; background: #ffffff; border: 3px solid {{ $subtitleColor === 'white' ? 'var(--purple-primary)' : 'transparent' }}; cursor: pointer;"></button>
                    </div>
                </div>

                <!-- ANIMATION GRID -->
                <div style="margin-bottom: 18px;">
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">ANIMATION · WORD-BY-WORD "TYPE"</label>
                    <div style="display: grid; grid-template-columns: repeat(4, 1fr); gap: 6px;">
                        @foreach(['POP', 'FADE', 'RISE', 'DROP', 'ZOOM', 'BLUR', 'TYPE', 'OFF'] as $anim)
                            <button type="button" wire:click="$set('captionAnimation', '{{ strtolower($anim) }}')" 
                                    style="padding: 6px 2px; font-size: 10px; font-weight: 800; border-radius: 8px; cursor: pointer;" 
                                    class="{{ $captionAnimation === strtolower($anim) ? 'btn' : 'btn-outline' }}">
                                {{ $anim }}
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- SLIDERS -->
                <div style="display: flex; flex-direction: column; gap: 14px; margin-bottom: 20px;">
                    <div>
                        <div class="row between" style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
                            <span>FONT SIZE</span>
                            <span>{{ $captionFontSize }}</span>
                        </div>
                        <input type="range" min="40" max="120" wire:model.live.debounce.300ms="captionFontSize" style="width:100%; accent-color: var(--purple-primary);">
                    </div>
                    <div>
                        <div class="row between" style="font-size: 11px; font-weight: 700; color: var(--text-muted); margin-bottom: 4px;">
                            <span>CAPTION Y POSITION</span>
                            <span>{{ $captionPosY }}</span>
                        </div>
                        <input type="range" min="200" max="1800" wire:model.live.debounce.300ms="captionPosY" style="width:100%; accent-color: var(--purple-primary);">
                    </div>
                </div>

                <!-- CTA INPUT & PRESETS -->
                <div style="margin-bottom: 20px;">
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 6px;">CALL TO ACTION OVERLAY (CTA)</label>
                    <input type="text" wire:model="ctaText" placeholder="Contoh: Follow Halaman Ini 🔔" style="margin-bottom: 8px;">
                    
                    <select wire:model.live="ctaText" style="width: 100%; padding: 8px 12px; border-radius: 8px; font-size: 11px; background: var(--bg-surface-subtle); border: 1px solid var(--border-color); color: var(--text-color); cursor: pointer;">
                        <option value="">-- Pilih Preset CTA Facebook --</option>
                        @foreach($this->ctaPresets() as $preset)
                            <option value="{{ $preset }}">{{ $preset }}</option>
                        @endforeach
                    </select>
                </div>


                <!-- EXPORT BUTTON -->
                <button type="button" wire:click="saveAndApprove" class="btn" style="width: 100%; padding: 14px; font-size: 14px; font-weight: 800; background: var(--purple-gradient); box-shadow: 0 4px 18px rgba(154, 85, 255, 0.4); border-radius: 99px;">
                    ⚡ Export &amp; Render Klip Ini
                </button>
            </div>
        </div>
    </div>

    <!-- CSS: 5 Premium Caption Animation Presets (Opus Clip / CapCut / Vizard style) -->
    <style>
        /* Base word span */
        .cw { display: inline-block; transition: color 0.08s, text-shadow 0.08s; }

        /* KARAOKE: active word neon highlight, others white */
        .anim-karaoke .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000; }
        .anim-karaoke .cw.active { animation: cwKaraoke 0.12s ease-out forwards; }
        @keyframes cwKaraoke { 0% { transform: scale(1.0); } 40% { transform: scale(1.18); } 100% { transform: scale(1.06); } }

        /* POP: CapCut spring pop on each new word */
        .anim-pop .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000; opacity: 0.55; }
        .anim-pop .cw.active { opacity: 1; animation: cwPop 0.18s cubic-bezier(0.34,1.56,0.64,1) forwards; }
        @keyframes cwPop { 0% { transform: scale(0.55) rotate(-4deg); opacity: 0; } 65% { transform: scale(1.12) rotate(1.5deg); } 100% { transform: scale(1.0) rotate(0); opacity: 1; } }

        /* GLOW: Vizard/Hormozi neon glow on active word */
        .anim-glow .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9); opacity: 0.6; }
        .anim-glow .cw.active { opacity: 1; animation: cwGlow 0.3s ease-in-out infinite alternate; }
        @keyframes cwGlow { 0% { text-shadow: 0 0 6px currentColor, 0 0 12px currentColor; } 100% { text-shadow: 0 0 20px currentColor, 0 0 40px currentColor, 0 0 60px currentColor; } }

        /* RISE: slide up entrance per new word */
        .anim-rise .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px 1px 0 #000; opacity: 0.5; }
        .anim-rise .cw.active { opacity: 1; animation: cwRise 0.2s ease-out forwards; }
        @keyframes cwRise { 0% { transform: translateY(14px); opacity: 0; } 100% { transform: translateY(0); opacity: 1; } }

        /* BLOCK: MrBeast/IShowSpeed background block highlight */
        .anim-block .cw { color: #ffffff; text-shadow: none; border-radius: 4px; padding: 1px 4px; transition: background 0.08s, color 0.08s; }
        .anim-block .cw.active { color: #000000 !important; background: var(--block-color, #ffff00); text-shadow: none; animation: cwBlock 0.1s ease-out forwards; }
        @keyframes cwBlock { 0% { transform: scale(0.9); } 100% { transform: scale(1.0); } }
    </style>

    <!-- JS Studio Player Controls & Live Subtitle Engine -->
    <script>
        if (typeof window.previewInterval === 'undefined') {
            window.previewInterval = null;
        }
        if (typeof window.lastCaptionActiveIdx === 'undefined') {
            window.lastCaptionActiveIdx = -1;
        }

        window.clipWordsData = @json($clipWords);

        window.addEventListener('words-updated', (event) => {
            if (event.detail && event.detail.words) {
                window.clipWordsData = event.detail.words;
                window.lastCaptionActiveIdx = -1; // force re-render
            }
        });

        window.cfColorMap = {
            yellow: '#ffff00',
            pink:   '#ff4d6d',
            orange: '#ff8c32',
            white:  '#ffffff',
        };

        window.renderCaptionGroup = function(groupWords, activeIdxInGroup, animation, activeColor) {
            const container = document.getElementById('caption-word-group');
            if (!container) return;

            container.className = 'anim-' + animation;
            container.style.setProperty('--block-color', activeColor);

            // Build spans
            let html = '';
            groupWords.forEach((w, i) => {
                const isActive = (i === activeIdxInGroup);
                const colorStyle = isActive ? `color:${activeColor};` : '';
                const activeClass = isActive ? ' active' : '';
                html += `<span class="cw${activeClass}" style="${colorStyle}">${w.word}</span>`;
            });
            container.innerHTML = html;
        };

        window.initLiveSubtitleSync = function() {
            const video = document.getElementById('editor-video');
            if (!video) return;

            video.addEventListener('timeupdate', () => {
                const words = window.clipWordsData;
                if (!words || words.length === 0) return;

                const currentMs = Math.round(video.currentTime * 1000);

                // Find active word index
                let activeIdx = -1;
                for (let i = 0; i < words.length; i++) {
                    if (currentMs >= words[i].start_ms && currentMs <= words[i].end_ms) {
                        activeIdx = i;
                        break;
                    }
                }

                // Between words: find nearest upcoming
                if (activeIdx === -1) {
                    for (let i = 0; i < words.length; i++) {
                        if (currentMs < words[i].start_ms) { activeIdx = i; break; }
                    }
                }

                if (activeIdx === -1 || activeIdx === window.lastCaptionActiveIdx) return;
                window.lastCaptionActiveIdx = activeIdx;

                // Build word group window: 3 before + active + 3 after
                const WINDOW = 3;
                const start = Math.max(0, activeIdx - WINDOW);
                const end   = Math.min(words.length - 1, activeIdx + WINDOW);
                const group = words.slice(start, end + 1);
                const activeInGroup = activeIdx - start;

                // Read animation + color from DOM data attributes (stays reactive to Livewire)
                const container = document.getElementById('caption-word-group');
                const animation = (container && container.dataset.animation) ? container.dataset.animation : 'karaoke';
                const colorKey  = (container && container.dataset.color) ? container.dataset.color : 'yellow';
                const activeColor = window.cfColorMap[colorKey] || '#ffff00';

                window.renderCaptionGroup(group, activeInGroup, animation, activeColor);
            });
        };

        if (document.readyState === 'loading') {
            document.addEventListener('DOMContentLoaded', window.initLiveSubtitleSync);
        } else {
            window.initLiveSubtitleSync();
        }

        window.seekEditorVideoTo = window.seekEditorVideoTo || function(ms) {
            if (typeof ms === 'undefined' || ms === null || ms < 0) return;
            const targetSeconds = ms / 1000;
            const video = document.getElementById('editor-video');
            if (video) {
                try { video.currentTime = targetSeconds; } catch (e) {}
            }
        };

        window.setStartToCurrent = window.setStartToCurrent || function(comp) {
            const video = document.getElementById('editor-video');
            if (video && comp) {
                const currentMs = Math.round(video.currentTime * 1000);
                comp.set('editStartMs', currentMs);
            }
        };

        window.setEndToCurrent = window.setEndToCurrent || function(comp) {
            const video = document.getElementById('editor-video');
            if (video && comp) {
                const currentMs = Math.round(video.currentTime * 1000);
                comp.set('editEndMs', currentMs);
            }
        };

        window.jumpToStart = window.jumpToStart || function(startMs) {
            seekEditorVideoTo(startMs);
        };

        window.jumpToEnd = window.jumpToEnd || function(endMs) {
            seekEditorVideoTo(endMs);
        };

        window.playPreview = window.playPreview || function(startMs, endMs) {
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
    </script>
</div>


