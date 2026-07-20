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
            <a href="/openreel/?video_url={{ urlencode('/videos/' . $candidate->video_id . '/source') }}" target="_blank" class="btn btn-outline" style="border-color: #3b82f6; color: #3b82f6; font-weight: 700;">
                <i class="ph ph-video" style="font-size: 16px;"></i> Edit di OpenReel
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

                <div class="row between" style="width: 100%; margin-bottom: 16px; align-items: center; flex-wrap: wrap; gap: 10px;">
                    <div style="display: flex; align-items: center; gap: 8px;">
                        <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted); text-transform: uppercase;">FRAMING MODE:</span>
                        <button type="button" 
                                wire:click="$set('cropMode', 'auto')"
                                style="padding: 5px 12px; font-size: 11px; font-weight: 800; border-radius: 99px; transition: all 0.15s ease;"
                                class="btn {{ $cropMode === 'auto' ? 'btn-primary' : 'btn-outline' }}">
                            <i class="ph ph-sparkle"></i> Auto AI Framing
                        </button>
                        <button type="button" 
                                wire:click="$set('cropMode', 'manual')"
                                style="padding: 5px 12px; font-size: 11px; font-weight: 800; border-radius: 99px; transition: all 0.15s ease;"
                                class="btn {{ $cropMode === 'manual' ? 'btn-primary' : 'btn-outline' }}">
                            <i class="ph ph-crop"></i> ✂ Pangkas Manual
                        </button>
                    </div>
                    <span class="badge badge-purple" style="padding: 4px 10px;">{{ strtoupper($renderFormat) }}</span>
                </div>

                @if($cropMode === 'auto')
                    <!-- 9:16 Simulated Mobile Player Screen (Auto AI Framing) -->
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
                @else
                    <!-- 16:9 Full Source Container + Draggable 9:16 Manual Crop Box -->
                    <div style="display: flex; flex-direction: column; align-items: center; width: 100%; margin-bottom: 20px;">
                        <div id="manual-crop-container" 
                             onclick="positionManualCropOnClick(event)"
                             style="width: 100%; max-width: 560px; height: 315px; background: #080c14; border-radius: 16px; position: relative; overflow: hidden; border: 2px solid var(--purple-primary, #6366f1); box-shadow: 0 12px 36px rgba(0,0,0,0.4); user-select: none;">
                            
                            <!-- Source 16:9 Video -->
                            <video id="editor-video-manual" controls preload="metadata" 
                                   onloadedmetadata="if (typeof seekEditorVideoTo === 'function') seekEditorVideoTo({{ $editStartMs }})"
                                   style="width: 100%; height: 100%; object-fit: contain; display: block;" 
                                   src="/videos/{{ $candidate->video_id }}/source">
                            </video>

                            <!-- 9:16 Draggable Overlay Window -->
                            <div id="manual-crop-window" 
                                 onmousedown="startManualCropDrag(event)"
                                 ontouchstart="startManualCropDrag(event)"
                                 style="
                                     position: absolute; 
                                     top: 0; 
                                     bottom: 0; 
                                     width: 177px; 
                                     left: calc({{ $manualCropX * 100 }}% - 88.5px);
                                     border: 2.5px solid #00f0ff; 
                                     background: rgba(0, 240, 255, 0.12); 
                                     box-shadow: 0 0 20px rgba(0, 240, 255, 0.5), inset 0 0 15px rgba(0, 240, 255, 0.2); 
                                     cursor: grab; 
                                     z-index: 10;
                                     display: flex;
                                     flex-direction: column;
                                     justify-content: space-between;
                                     touch-action: none;
                                 ">
                                <!-- Header Label / Handle -->
                                <div style="background: rgba(0, 240, 255, 0.9); color: #000; font-size: 9px; font-weight: 900; text-transform: uppercase; padding: 4px; text-align: center; letter-spacing: 0.05em; font-family: var(--font-mono); pointer-events: none;">
                                    ⟷ 9:16 FRAME CROP
                                </div>

                                <!-- Real-Time Subtitle Overlay inside 9:16 window -->
                                @if($burnSubtitles === 'on')
                                    <div id="subtitle-overlay-manual" style="position: absolute; bottom: {{ ($captionPosY / 1920) * 100 }}%; left: 4%; right: 4%; text-align: center; pointer-events: none; z-index: 12;">
                                        <div id="caption-word-group-manual"
                                             data-animation="{{ $captionAnimation }}"
                                             data-color="{{ $subtitleColor }}"
                                             data-fontsize="{{ ($captionFontSize / 1920) * 315 }}"
                                             style="
                                                 font-family: 'Outfit', sans-serif;
                                                 font-size: {{ ($captionFontSize / 1920) * 315 }}px;
                                                 font-weight: 900;
                                                 text-transform: uppercase;
                                                 letter-spacing: 0.03em;
                                                 line-height: 1.3;
                                                 display: flex;
                                                 flex-wrap: wrap;
                                                 justify-content: center;
                                                 gap: 0 4px;
                                                 min-height: 1.5em;
                                             ">
                                            <!-- JS syncs word spans here -->
                                        </div>
                                    </div>
                                @endif

                                <!-- Bottom Position Badge -->
                                <div style="background: rgba(0, 0, 0, 0.75); color: #00f0ff; font-size: 9px; font-weight: 800; padding: 3px; text-align: center; font-family: var(--font-mono); pointer-events: none; border-top: 1px solid rgba(0, 240, 255, 0.4);">
                                    POS X: <span id="manual-crop-x-label">{{ round($manualCropX * 100, 1) }}%</span>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Instruction Banner -->
                        <div style="margin-top: 8px; font-size: 11px; font-weight: 700; color: var(--text-muted); text-align: center;">
                            💡 <span style="color: #00f0ff;">Tarik bingkai cyan ke kiri / kanan</span> untuk menentukan area 9:16 yang akan dipangkas.
                        </div>
                    </div>
                @endif

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

                <!-- Jiggle Effect Toggle -->
                <div style="display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px;">
                    <span style="font-size: 10px; font-weight: 700; color: var(--text-muted); font-family: var(--font-mono); letter-spacing: 0.1em;">JIGGLE EFFECT</span>
                    <button id="jiggle-toggle-btn"
                            type="button"
                            onclick="toggleJiggleEffect(this)"
                            style="padding: 4px 14px; font-size: 10px; font-weight: 800; border-radius: 99px; cursor: pointer; transition: all 0.15s ease;"
                            class="btn">
                        ✦ ON
                    </button>
                </div>
            </div>



            <!-- VISUAL TIMELINE FRAMING -->
            <div class="panel" style="padding: 20px; background: var(--bg-surface);">
                <div class="row between" style="margin-bottom: 12px;">
                    <span style="font-size: 11px; font-weight: 800; font-family: var(--font-mono); color: var(--text-muted);">TIMELINE FRAMING</span>
                    <span id="timeline-duration" style="font-size: 10px; font-weight: 700; color: var(--purple-primary); font-family: var(--font-mono);">DUR: --:--</span>
                </div>

                <!-- Start / End time labels -->
                <div style="display: flex; justify-content: space-between; align-items: flex-end; margin-bottom: 8px;">
                    <div>
                        <div style="font-size: 9px; color: var(--text-muted); font-weight: 700; margin-bottom: 2px;">▶ START</div>
                        <div id="tl-start-label" style="font-size: 15px; font-weight: 900; color: #4ade80; font-family: var(--font-mono); letter-spacing: 0.05em;">00:00</div>
                    </div>
                    <div style="text-align: right;">
                        <div style="font-size: 9px; color: var(--text-muted); font-weight: 700; margin-bottom: 2px;">END ⬛</div>
                        <div id="tl-end-label" style="font-size: 15px; font-weight: 900; color: #f87171; font-family: var(--font-mono); letter-spacing: 0.05em;">00:00</div>
                    </div>
                </div>

                <!-- Timeline track -->
                <div id="timeline-track"
                     style="position: relative; height: 38px; background: var(--bg-surface-subtle); border-radius: 8px; cursor: pointer; border: 1px solid var(--border-color); margin-bottom: 12px; user-select: none;">

                    <!-- Selection region -->
                    <div id="tl-selection"
                         style="position: absolute; top: 0; bottom: 0; background: rgba(74,222,128,0.15); border-left: 2px solid #4ade80; border-right: 2px solid #f87171; border-radius: 4px; pointer-events: none;">
                    </div>

                    <!-- Playhead -->
                    <div id="tl-playhead"
                         style="position: absolute; top: -5px; bottom: -5px; width: 2px; background: var(--purple-primary); border-radius: 99px; pointer-events: none; z-index: 5; box-shadow: 0 0 8px rgba(154,85,255,0.8); transition: left 0.05s linear;">
                        <div style="position: absolute; top: -1px; left: 50%; transform: translateX(-50%); width: 8px; height: 8px; background: var(--purple-primary); border-radius: 50%; box-shadow: 0 0 6px rgba(154,85,255,0.9);"></div>
                    </div>

                    <!-- Start handle -->
                    <div id="tl-handle-start"
                         title="Drag to set Start"
                         style="position: absolute; top: 0; bottom: 0; width: 12px; background: #4ade80; border-radius: 4px 2px 2px 4px; cursor: ew-resize; transform: translateX(-50%); z-index: 4; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                            <div style="width: 2px; height: 14px; background: rgba(0,0,0,0.3); border-radius: 1px;"></div>
                        </div>
                    </div>

                    <!-- End handle -->
                    <div id="tl-handle-end"
                         title="Drag to set End"
                         style="position: absolute; top: 0; bottom: 0; width: 12px; background: #f87171; border-radius: 2px 4px 4px 2px; cursor: ew-resize; transform: translateX(-50%); z-index: 4; box-shadow: 0 2px 6px rgba(0,0,0,0.3);">
                        <div style="position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;">
                            <div style="width: 2px; height: 14px; background: rgba(0,0,0,0.3); border-radius: 1px;"></div>
                        </div>
                    </div>
                </div>

                <!-- Shortcut buttons -->
                <div class="row" style="gap: 8px;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="setStartToCurrent(Livewire.find('{{ $this->getId() }}'))" style="flex: 1; font-size: 11px;">
                        <i class="ph ph-map-pin-line"></i> Set Start
                    </button>
                    <button type="button" class="btn btn-sm btn-outline" onclick="setEndToCurrent(Livewire.find('{{ $this->getId() }}'))" style="flex: 1; font-size: 11px;">
                        <i class="ph ph-flag-banner"></i> Set End
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
                    <label style="font-size: 11px; font-weight: 700; color: var(--text-muted); display: block; margin-bottom: 8px;">ANIMATION PRESET (LIVE)</label>
                    <div style="display: grid; grid-template-columns: repeat(3, 1fr); gap: 6px;">
                        @foreach(['KARAOKE', 'POP', 'GLOW', 'RISE', 'BLOCK', 'OFF'] as $anim)
                            <button type="button" wire:click="$set('captionAnimation', '{{ strtolower($anim) }}')" 
                                    style="padding: 7px 4px; font-size: 10px; font-weight: 800; border-radius: 8px; cursor: pointer;" 
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

    <!-- CSS: 6 Premium Caption Animation Presets (Opus Clip / CapCut / Vizard style) -->
    <style>
        /* Base word span — no default transition on transform to avoid fighting animations */
        .cw { display: inline-block; transition: color 0.08s ease, opacity 0.1s ease; transform-origin: center bottom; }

        /* KARAOKE: active word neon highlight scale bounce, others white */
        .anim-karaoke .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px -1px 0 #000, -1px 1px 0 #000, 1px 1px 0 #000; }
        .anim-karaoke .cw.active { animation: cwKaraoke 0.15s ease-out forwards; }
        @keyframes cwKaraoke { 0% { transform: scale(1.0); } 45% { transform: scale(1.22); } 100% { transform: scale(1.08); } }

        /* POP: CapCut spring bounce pop per word */
        .anim-pop .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px 1px 0 #000; opacity: 0.5; transform: scale(0.85); }
        .anim-pop .cw.active { opacity: 1; animation: cwPop 0.22s cubic-bezier(0.34,1.56,0.64,1) forwards; }
        @keyframes cwPop { 0% { transform: scale(0.5) rotate(-5deg); opacity: 0; } 70% { transform: scale(1.14) rotate(2deg); opacity: 1; } 100% { transform: scale(1.0) rotate(0); } }

        /* GLOW: Vizard/Hormozi neon pulse on active word */
        .anim-glow .cw { color: #ffffff; text-shadow: 0 1px 4px rgba(0,0,0,0.8); opacity: 0.55; }
        .anim-glow .cw.active { opacity: 1; animation: cwGlow 0.35s ease-in-out infinite alternate; }
        @keyframes cwGlow {
            0%   { text-shadow: 0 0 4px currentColor, 0 0 8px currentColor, 0 1px 4px rgba(0,0,0,0.9); }
            100% { text-shadow: 0 0 16px currentColor, 0 0 32px currentColor, 0 0 52px currentColor, 0 1px 4px rgba(0,0,0,0.9); }
        }

        /* RISE: each new word slides up from below */
        .anim-rise .cw { color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px 1px 0 #000; opacity: 0.45; }
        .anim-rise .cw.active { opacity: 1; animation: cwRise 0.22s cubic-bezier(0.22,1,0.36,1) forwards; }
        @keyframes cwRise { 0% { transform: translateY(16px) scale(0.9); opacity: 0; } 100% { transform: translateY(0) scale(1); opacity: 1; } }

        /* BLOCK: solid neon background box on active word (MrBeast / IShowSpeed style) */
        .anim-block .cw { color: #ffffff; text-shadow: none; border-radius: 5px; padding: 1px 5px; }
        .anim-block .cw.active { color: #000000 !important; background: var(--block-color, #ffff00); text-shadow: none; animation: cwBlock 0.12s ease-out forwards; }
        @keyframes cwBlock { 0% { transform: scale(0.88); } 60% { transform: scale(1.06); } 100% { transform: scale(1.0); } }

        /* OFF: show only active word, no animation */
        .anim-off .cw { display: none; }
        .anim-off .cw.active { display: inline-block; color: #ffffff; text-shadow: 0 1px 6px rgba(0,0,0,0.9), -1px -1px 0 #000, 1px 1px 0 #000; }

        /* Canvas Jiggle-Drop — phone frame drops & bounces when Play is clicked */
        @keyframes canvasJiggleDrop {
            0%   { transform: translateY(0px)  rotate(0deg);    }
            12%  { transform: translateY(-6px) rotate(-0.4deg); }
            30%  { transform: translateY(8px)  rotate( 0.35deg);}
            50%  { transform: translateY(-3px) rotate(-0.2deg); }
            68%  { transform: translateY(2px)  rotate( 0.1deg); }
            84%  { transform: translateY(-1px) rotate(0deg);    }
            100% { transform: translateY(0px)  rotate(0deg);    }
        }
        #live-canvas-frame.canvas-jiggle {
            animation: canvasJiggleDrop 0.5s cubic-bezier(0.36, 0.07, 0.19, 0.97) forwards;
        }
    </style>

    <!-- JS Studio Player Controls & Live Subtitle Engine -->
    <script>
        if (typeof window.previewInterval === 'undefined') window.previewInterval = null;
        if (typeof window.lastCaptionActiveIdx === 'undefined') window.lastCaptionActiveIdx = -1;
        if (typeof window.cfTimeupdateHandler === 'undefined') window.cfTimeupdateHandler = null;
        if (typeof window.cfJiggleEnabled === 'undefined') window.cfJiggleEnabled = true;

        // Toggle jiggle-drop effect ON/OFF
        window.toggleJiggleEffect = function(btn) {
            window.cfJiggleEnabled = !window.cfJiggleEnabled;
            btn.textContent = window.cfJiggleEnabled ? '✦ ON' : '✦ OFF';
            btn.className   = window.cfJiggleEnabled
                ? 'btn'
                : 'btn-outline';
            btn.style.cssText = 'padding:4px 14px; font-size:10px; font-weight:800; border-radius:99px; cursor:pointer; transition:all 0.15s ease;';
        };

        window.clipWordsData = @json($clipWords);

        window.addEventListener('words-updated', (event) => {
            if (event.detail && event.detail.words) {
                window.clipWordsData = event.detail.words;
                window.lastCaptionActiveIdx = -1;
            }
        });

        window.cfColorMap = { yellow:'#ffff00', pink:'#ff4d6d', orange:'#ff8c32', white:'#ffffff' };

        window.getActiveVideo = function() {
            return document.getElementById('editor-video-manual') || document.getElementById('editor-video');
        };

        // ✅ FIX: Smart DOM diff — only rebuild innerHTML when word GROUP changes;
        //         within same group, only swap active class + force reflow to re-trigger animation.
        window.renderCaptionGroup = function(groupWords, activeInGroup, animation, activeColor) {
            ['caption-word-group', 'caption-word-group-manual'].forEach(id => {
                const container = document.getElementById(id);
                if (!container) return;

                container.className = 'anim-' + animation;
                container.style.setProperty('--block-color', activeColor);

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

        // ✅ Singleton event listener for Live Subtitle Sync
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
                const activeColor = window.cfColorMap[colorKey] || '#ffff00';

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

        // ✅ Visual Timeline Scrubber Engine
        window.initTimelineScrubber = function() {
            const video = window.getActiveVideo();
            const track = document.getElementById('timeline-track');
            if (!video || !track) return;

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

                // Jiggle-drop effect on 9:16 canvas frame
                const frame = document.getElementById('live-canvas-frame');
                if (frame && window.cfJiggleEnabled) {
                    frame.classList.remove('canvas-jiggle');
                    void frame.offsetWidth; // force reflow so animation always restarts
                    frame.classList.add('canvas-jiggle');
                }

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

        // ✅ Interactive 9:16 Manual Crop Drag Engine (Direct Inline + Global Window Handlers)
        window.startManualCropDrag = function(e) {
            const cropBox = document.getElementById('manual-crop-window');
            const container = document.getElementById('manual-crop-container');
            const label = document.getElementById('manual-crop-x-label');
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
                const maxLeft = containerRect.width - boxRect.width;

                let newLeft = startLeft + deltaX;
                newLeft = Math.max(0, Math.min(maxLeft, newLeft));

                cropBox.style.left = newLeft + 'px';

                const centerX = newLeft + (boxRect.width / 2);
                const normPct = Math.max(0, Math.min(1, centerX / containerRect.width));

                if (label) {
                    label.textContent = (normPct * 100).toFixed(1) + '%';
                }

                if (window.manualCropTimer) clearTimeout(window.manualCropTimer);
                window.manualCropTimer = setTimeout(function() {
                    if (window.Livewire) {
                        const rootElem = container.closest('[wire\\:id]');
                        if (rootElem) {
                            const compId = rootElem.getAttribute('wire:id');
                            const comp = Livewire.find(compId);
                            if (comp) {
                                comp.set('manualCropX', parseFloat(normPct.toFixed(4)));
                            }
                        }
                    }
                }, 100);
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

            if (e.cancelable) {
                e.preventDefault();
            }
        };

        window.positionManualCropOnClick = function(e) {
            if (e.target.closest('#manual-crop-window')) return;
            const container = document.getElementById('manual-crop-container');
            const cropBox = document.getElementById('manual-crop-window');
            const label = document.getElementById('manual-crop-x-label');
            if (!container || !cropBox) return;

            const rect = container.getBoundingClientRect();
            const clickX = e.clientX - rect.left;
            const boxWidth = cropBox.offsetWidth;
            const maxLeft = rect.width - boxWidth;

            let newLeft = clickX - (boxWidth / 2);
            newLeft = Math.max(0, Math.min(maxLeft, newLeft));

            cropBox.style.left = newLeft + 'px';

            const centerX = newLeft + (boxWidth / 2);
            const normPct = Math.max(0, Math.min(1, centerX / rect.width));

            if (label) {
                label.textContent = (normPct * 100).toFixed(1) + '%';
            }

            if (window.Livewire) {
                const rootElem = container.closest('[wire\\:id]');
                if (rootElem) {
                    const compId = rootElem.getAttribute('wire:id');
                    const comp = Livewire.find(compId);
                    if (comp) {
                        comp.set('manualCropX', parseFloat(normPct.toFixed(4)));
                    }
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


