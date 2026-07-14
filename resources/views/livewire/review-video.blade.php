<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Page Header & Navigation -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <a href="/" class="muted" style="font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s; color: rgba(245,239,228,0.6);" onmouseover="this.style.color='var(--accent)'" onmouseout="this.style.color='rgba(245,239,228,0.6)'">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Kembali ke Dashboard
            </a>
            <h1 class="page-title" style="margin-top:8px;">Review &amp; Edit Kandidat</h1>
            <p class="page-sub" style="margin-bottom:0; display: flex; align-items: center; gap: 8px;">
                <span class="muted" style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $video->source_ref ?? '—' }}</span>
                @if($video->duration_seconds)
                    <span style="color: rgba(245,239,228,0.2);">|</span>
                    <span class="muted font-semibold">{{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}</span>
                @endif
                <span style="color: rgba(245,239,228,0.2);">|</span>
                <span style="color: var(--accent); font-weight: 600;">{{ $candidates->count() }} Kandidat Terdeteksi</span>
            </p>
        </div>
        <div class="row" style="gap: 12px;">
            <button type="button" class="btn btn-sm btn-outline" wire:click="createCustomCandidate" style="color: #f5efe4; border-color: rgba(245,239,228,0.2); background: rgba(255,255,255,0.03);">
                ➕ Buat Klip Kustom
            </button>
            @if($poll)
                <span class="badge badge-amber" style="background: var(--tile-1); color: var(--ink); border: 1px solid rgba(0,0,0,0.06);">
                    <span class="spin"></span>&nbsp;Mengekspor &amp; Merender Klip
                </span>
            @endif
        </div>
    </div>

    @if($flash)<div class="flash">{{ $flash }}</div>@endif
    @if($error)<div class="flash flash-error">{{ $error }}</div>@endif

    <!-- Split Workspace -->
    <div class="grid" style="grid-template-columns: 420px 1fr; gap: 28px; align-items: start;">
        
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
                </div>
            @else
                <!-- Edit Mode: Sticky Clip Editor Workspace -->
                <div class="panel grid" style="gap: 16px; padding: 20px; background: var(--tile-3); border-color: rgba(0,0,0,0.05); color: var(--ink);">
                    <h4 style="margin: 0; font-family: var(--serif); font-style: italic; font-size: 24px; font-weight: 700; color: var(--ink); display: flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--ink);"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        {{ $editingId === -1 ? 'Buat Klip Kustom' : 'Edit Klip #' . $editingId }}
                    </h4>
                    
                    <div style="border-radius: 14px; overflow: hidden; background: #000; border: 1.5px solid var(--ink); position: relative;">
                        <video id="editor-video" controls preload="metadata" style="width:100%; display: block; aspect-ratio: 16/9;" src="/videos/{{ $video->id }}/source">
                        </video>
                    </div>

                    <!-- Quick In/Out Buttons -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="setStartToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu awal">
                            📍 Set Awal (In)
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="setEndToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu akhir">
                            🏁 Set Akhir (Out)
                        </button>
                    </div>

                    <!-- Seek & Preview Actions -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm btn-primary" onclick="playPreview()" style="flex: 2; padding: 8px 12px; font-size: 11px; border-color: var(--ink); background: var(--ink); color: #f5efe4;">
                            ▶ Preview Klip
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="jumpToStart()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker awal">
                            ⏮ Awal
                        </button>
                        <button type="button" class="btn btn-sm btn-outline" onclick="jumpToEnd()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker akhir">
                            ⏭ Akhir
                        </button>
                    </div>

                    <!-- Start / End Millisecond Fields -->
                    <div class="row" style="gap: 12px;">
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase; color: var(--ink);">Start (ms)</label>
                            <input type="number" wire:model.live="editStartMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: #ffffff; border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px;">
                        </div>
                        <div style="flex: 1;">
                            <label style="font-size: 11px; font-family: var(--mono); font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase; color: var(--ink);">End (ms)</label>
                            <input type="number" wire:model.live="editEndMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: #ffffff; border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px;">
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
                        <textarea wire:model="editRationale" rows="3" style="width:100%; padding: 8px 12px; border-radius: 8px; background: #ffffff; border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13px; resize: vertical; line-height: 1.4;"></textarea>
                    </div>

                    <!-- Save / Cancel Buttons -->
                    <div class="row" style="gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn btn-sm btn-outline" wire:click="closeEditor" style="flex: 1; border-color: var(--ink); color: var(--ink);">
                            Batal
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="{{ $editingId === -1 ? 'saveCustomCandidate' : 'saveCandidate' }}" style="flex: 1; border-color: var(--ink); background: var(--ink); color: #f5efe4;">
                            Simpan
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Side: Configuration & Candidates -->
        <div class="grid" style="gap: 20px;">
            
            <!-- CTA Configurator -->
            <div class="panel grid" style="gap: 14px; background: var(--tile-6); border-color: rgba(0,0,0,0.05); color: var(--ink);">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-family: var(--serif); font-style: italic; font-weight: 700; font-size: 20px; color: var(--ink);">Call-to-Action (CTA) Overlay</label>
                    <span style="font-size: 12px; color: var(--muted); font-weight: 500;">Teks CTA ini akan di-burn secara permanen di bagian atas klip vertikal yang Anda setujui.</span>
                </div>
                
                <input type="text" wire:model="ctaText" maxlength="120"
                       placeholder="Masukkan CTA khusus (cth. BELI SEKARANG DI STEAM)"
                       style="width: 100%; padding: 12px 16px; border-radius: 12px; background: #ffffff;
                              border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;">
                
                <div class="grid" style="gap: 8px;">
                    <span style="font-size: 11px; font-family: var(--mono); font-weight: 700; text-transform: uppercase; color: var(--ink);">Pilihan Preset Cepat</span>
                    <div class="row" style="gap: 8px; flex-wrap: wrap;">
                        @foreach($this->ctaPresets() as $preset)
                            <button type="button" class="btn btn-sm btn-outline"
                                    wire:click="$set('ctaText', @js($preset))"
                                    style="font-size: 11px; padding: 6px 12px; background: rgba(255,255,255,0.3); font-weight: 600; border-radius: 8px; border-color: rgba(0,0,0,0.12); color: var(--ink);">
                                {{ $preset }}
                            </button>
                        @endforeach
                    </div>
                </div>
                
                @if(trim($ctaText) === '')
                    <div style="font-size: 11.5px; color: #b45309; display: flex; gap: 6px; align-items: center; font-weight: 700;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>
                        Teks CTA kosong. Sangat direkomendasikan menyertakan CTA untuk materi kampanye.
                    </div>
                @endif
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
                            ? 'border-left: 5px solid #16a34a; box-shadow: 0 4px 20px rgba(0,0,0,0.05);' 
                            : ($c->hook_score >= 50 
                                ? 'border-left: 5px solid #d97706; box-shadow: 0 4px 20px rgba(0,0,0,0.05);' 
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
                                    <!-- Edit Trigger Button -->
                                    <button class="btn btn-sm btn-outline" wire:click="selectCandidate({{ $c->id }})" title="Edit stempel waktu klip ini secara manual">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-sm btn-primary" style="border-color: #16a34a; background: #16a34a; color: #ffffff;"
                                            wire:click="approve({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="approve({{ $c->id }})">
                                        Approve &amp; Render
                                    </button>
                                @endif
                                @if(in_array($c->status, ['pending','approved']))
                                    <button class="btn btn-sm btn-outline" style="border-color: #dc2626; color: #dc2626;"
                                            wire:click="reject({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="reject({{ $c->id }})">
                                        Reject
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- LLM Rationale text -->
                        @if($c->score_rationale)
                            <div style="margin-top: 14px; font-size: 13px; border-top: 1px solid var(--line); padding-top: 12px; line-height: 1.6; color: var(--muted); display: flex; gap: 8px; align-items: flex-start;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--ink)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 3px;">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
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
        document.addEventListener('DOMContentLoaded', () => {
            let previewInterval = null;

            // Listen for candidate loading events from Livewire
            window.addEventListener('candidate-selected', (event) => {
                const startMs = event.detail.startMs;
                const video = document.getElementById('editor-video');
                if (video) {
                    video.currentTime = startMs / 1000;
                }
            });

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
                const video = document.getElementById('editor-video');
                if (video) {
                    const startMs = @this.get('editStartMs');
                    video.currentTime = startMs / 1000;
                }
            };

            window.jumpToEnd = () => {
                const video = document.getElementById('editor-video');
                if (video) {
                    const endMs = @this.get('editEndMs');
                    video.currentTime = endMs / 1000;
                }
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
        });
    </script>
</div>
