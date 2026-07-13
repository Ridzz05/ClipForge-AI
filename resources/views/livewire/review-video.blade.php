<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Page Header & Navigation -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <a href="/" class="muted" style="font-size: 13px; font-weight: 600; display: inline-flex; align-items: center; gap: 6px; transition: color 0.2s;" onmouseover="this.style.color='var(--accent-start)'" onmouseout="this.style.color='var(--muted)'">
                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="19" y1="12" x2="5" y2="12"/><polyline points="12 19 5 12 12 5"/></svg>
                Kembali ke Dashboard
            </a>
            <h1 class="page-title" style="margin-top:8px;">Review &amp; Edit Kandidat</h1>
            <p class="page-sub" style="margin-bottom:0; display: flex; align-items: center; gap: 8px;">
                <span class="muted" style="max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">{{ $video->source_ref ?? '—' }}</span>
                @if($video->duration_seconds)
                    <span style="color: var(--border);">|</span>
                    <span class="muted font-semibold">{{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}</span>
                @endif
                <span style="color: var(--border);">|</span>
                <span style="color: var(--accent-start); font-weight: 600;">{{ $candidates->count() }} Kandidat Terdeteksi</span>
            </p>
        </div>
        <div class="row" style="gap: 12px;">
            <button type="button" class="btn btn-sm btn-primary" wire:click="createCustomCandidate" style="padding: 8px 16px; box-shadow: 0 4px 15px rgba(129, 140, 248, 0.2);">
                ➕ Buat Klip Kustom
            </button>
            @if($poll)
                <span class="badge badge-amber" style="box-shadow: 0 0 12px rgba(245, 158, 11, 0.25);">
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
                <div class="panel" style="padding: 16px;">
                    <h4 style="margin: 0 0 12px; font-family: 'Outfit', sans-serif; font-size: 14px; font-weight: 700; letter-spacing: 0.2px; text-transform: uppercase; color: var(--muted);">Video Sumber Asli</h4>
                    <div style="border-radius: 12px; overflow: hidden; background: #000; box-shadow: 0 8px 24px rgba(0,0,0,0.5); border: 1px solid var(--border);">
                        <video controls id="editor-video" preload="metadata" style="width:100%; display: block; aspect-ratio: 16/9;" src="/videos/{{ $video->id }}/source">
                            Browser Anda tidak mendukung HTML5 video.
                        </video>
                    </div>
                    <p class="muted" style="font-size: 12px; line-height: 1.5; margin: 12px 0 0;">
                        Gunakan pemutar di atas untuk meninjau bagian stempel waktu di kanan. Untuk mengedit stempel waktu klip secara akurat, klik tombol **Edit** pada kartu klip yang bersangkutan.
                    </p>
                </div>
            @else
                <!-- Edit Mode: Sticky Clip Editor Workspace -->
                <div class="panel grid" style="gap: 16px; padding: 18px; border-color: var(--accent-start);">
                    <h4 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 15px; font-weight: 800; letter-spacing: -0.1px; color: var(--accent-start); display: flex; align-items: center; gap: 6px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"/><path d="M16.5 3.5a2.121 2.121 0 0 1 3 3L7 19l-4 1 1-4L16.5 3.5z"/></svg>
                        {{ $editingId === -1 ? 'Buat Klip Kustom' : 'Edit Rentang Klip #' . $editingId }}
                    </h4>
                    
                    <div style="border-radius: 10px; overflow: hidden; background: #000; border: 1px solid var(--border); position: relative;">
                        <video id="editor-video" controls preload="metadata" style="width:100%; display: block; aspect-ratio: 16/9;" src="/videos/{{ $video->id }}/source">
                        </video>
                    </div>

                    <!-- Quick In/Out Buttons -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm" onclick="setStartToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu awal">
                            📍 Set Awal (In)
                        </button>
                        <button type="button" class="btn btn-sm" onclick="setEndToCurrent()" style="flex: 1; padding: 8px 10px; font-size: 11px;" title="Gunakan posisi player saat ini sebagai stempel waktu akhir">
                            🏁 Set Akhir (Out)
                        </button>
                    </div>

                    <!-- Seek & Preview Actions -->
                    <div class="row" style="gap: 8px; justify-content: center;">
                        <button type="button" class="btn btn-sm btn-green" onclick="playPreview()" style="flex: 2; padding: 8px 12px; font-size: 11px;">
                            ▶ Putar Preview Klip
                        </button>
                        <button type="button" class="btn btn-sm" onclick="jumpToStart()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker awal">
                            ⏮ Awal
                        </button>
                        <button type="button" class="btn btn-sm" onclick="jumpToEnd()" style="flex: 1; padding: 8px; font-size: 11px;" title="Lompat ke marker akhir">
                            ⏭ Akhir
                        </button>
                    </div>

                    <!-- Start / End Millisecond Fields -->
                    <div class="row" style="gap: 12px;">
                        <div style="flex: 1;">
                            <label class="muted" style="font-size: 11px; font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase;">Start (ms)</label>
                            <input type="number" wire:model.live="editStartMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: var(--panel-2-solid); border: 1px solid var(--border); color: var(--text); font-family: inherit; font-size: 13px;">
                        </div>
                        <div style="flex: 1;">
                            <label class="muted" style="font-size: 11px; font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase;">End (ms)</label>
                            <input type="number" wire:model.live="editEndMs" style="width:100%; padding: 8px 10px; border-radius: 8px; background: var(--panel-2-solid); border: 1px solid var(--border); color: var(--text); font-family: inherit; font-size: 13px;">
                        </div>
                    </div>

                    <!-- Hook Score slider -->
                    <div>
                        <div class="row between" style="margin-bottom: 4px;">
                            <label class="muted" style="font-size: 11px; font-weight: 700; text-transform: uppercase;">Hook Score</label>
                            <span style="font-weight: 800; color: var(--accent-start); font-size: 13px;">{{ $editHookScore }}%</span>
                        </div>
                        <input type="range" min="0" max="100" wire:model="editHookScore" style="width:100%; accent-color: var(--accent-start); cursor: pointer;">
                    </div>

                    <!-- Rationale textarea -->
                    <div>
                        <label class="muted" style="font-size: 11px; font-weight: 700; display: block; margin-bottom: 4px; text-transform: uppercase;">Deskripsi / Rationale</label>
                        <textarea wire:model="editRationale" rows="3" style="width:100%; padding: 8px 12px; border-radius: 8px; background: var(--panel-2-solid); border: 1px solid var(--border); color: var(--text); font-family: inherit; font-size: 13px; resize: vertical; line-height: 1.4;"></textarea>
                    </div>

                    <!-- Save / Cancel Buttons -->
                    <div class="row" style="gap: 10px; margin-top: 6px;">
                        <button type="button" class="btn btn-sm" wire:click="closeEditor" style="flex: 1;">
                            Batal
                        </button>
                        <button type="button" class="btn btn-sm btn-primary" wire:click="{{ $editingId === -1 ? 'saveCustomCandidate' : 'saveCandidate' }}" style="flex: 1;">
                            Simpan
                        </button>
                    </div>
                </div>
            @endif
        </div>

        <!-- Right Side: Configuration & Candidates -->
        <div class="grid" style="gap: 20px;">
            
            <!-- CTA Configurator -->
            <div class="panel grid" style="gap: 14px;">
                <div style="display: flex; flex-direction: column; gap: 4px;">
                    <label style="font-family: 'Outfit', sans-serif; font-weight: 700; font-size: 14px; color: var(--text);">Call-to-Action (CTA) Overlay</label>
                    <span class="muted" style="font-size: 11px;">Teks CTA ini akan di-burn secara permanen di bagian atas klip vertikal yang Anda setujui.</span>
                </div>
                
                <input type="text" wire:model="ctaText" maxlength="120"
                       placeholder="Masukkan CTA khusus (cth. BELI SEKARANG DI STEAM)"
                       style="width: 100%; padding: 12px 16px; border-radius: 10px; background: var(--panel-2);
                              border: 1px solid var(--border); color: var(--text); font-family: inherit; font-size: 14px;">
                
                <div class="grid" style="gap: 8px;">
                    <span class="muted" style="font-size: 11px; font-weight: 600; text-transform: uppercase;">Pilihan Preset Cepat</span>
                    <div class="row" style="gap: 8px; flex-wrap: wrap;">
                        @foreach($this->ctaPresets() as $preset)
                            <button type="button" class="btn btn-sm"
                                    wire:click="$set('ctaText', @js($preset))"
                                    style="font-size: 11px; padding: 6px 12px; background: rgba(255,255,255,0.02); font-weight: 500; border-radius: 8px;">
                                {{ $preset }}
                            </button>
                        @endforeach
                    </div>
                </div>
                
                @if(trim($ctaText) === '')
                    <div style="font-size: 11px; color: var(--amber); display: flex; gap: 6px; align-items: center; font-weight: 500;">
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
                            ? 'border-left: 4px solid var(--green); box-shadow: 0 4px 20px rgba(16, 185, 129, 0.05);' 
                            : ($c->hook_score >= 50 
                                ? 'border-left: 4px solid var(--amber); box-shadow: 0 4px 20px rgba(245, 158, 11, 0.05);' 
                                : 'border-left: 4px solid var(--muted);');
                    @endphp
                    <div class="panel" wire:key="cand-{{ $c->id }}" style="{{ $scoreGlow }} transition: transform 0.2s ease;">
                        <div class="row between" style="align-items: center; gap: 20px;">
                            
                            <!-- Score & Time Frame info -->
                            <div class="row" style="gap: 20px; align-items: center; flex: 1;">
                                <!-- Glowing Score Badge -->
                                <div style="text-align: center; min-width: 64px; background: rgba(255,255,255,0.02); padding: 10px; border-radius: 12px; border: 1px solid var(--border);">
                                    <div style="font-family: 'Outfit', sans-serif; font-size: 28px; font-weight: 800; line-height: 1;
                                         color: {{ $c->hook_score >= 75 ? 'var(--green)' : ($c->hook_score >= 50 ? 'var(--amber)' : 'var(--muted)') }};
                                         text-shadow: 0 0 10px {{ $c->hook_score >= 75 ? 'rgba(16,185,129,0.3)' : ($c->hook_score >= 50 ? 'rgba(245,158,11,0.3)' : 'transparent') }};">
                                        {{ $c->hook_score }}
                                    </div>
                                    <div class="muted" style="font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.5px; margin-top: 4px;">Score</div>
                                </div>
                                
                                <div>
                                    <div style="font-size: 15px; font-weight: 700; color: var(--text);">
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
                                    <button class="btn btn-sm" wire:click="selectCandidate({{ $c->id }})" title="Edit stempel waktu klip ini secara manual">
                                        ✏️ Edit
                                    </button>
                                    <button class="btn btn-green" style="padding: 8px 16px; font-size: 12px;"
                                            wire:click="approve({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="approve({{ $c->id }})">
                                        Approve &amp; Render
                                    </button>
                                @endif
                                @if(in_array($c->status, ['pending','approved']))
                                    <button class="btn btn-red" style="padding: 8px 16px; font-size: 12px;"
                                            wire:click="reject({{ $c->id }})"
                                            wire:loading.attr="disabled" wire:target="reject({{ $c->id }})">
                                        Reject
                                    </button>
                                @endif
                            </div>
                        </div>

                        <!-- LLM Rationale text -->
                        @if($c->score_rationale)
                            <div style="margin-top: 14px; font-size: 13px; border-top: 1px solid var(--border); padding-top: 12px; line-height: 1.6; color: var(--muted); display: flex; gap: 8px; align-items: flex-start;">
                                <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="var(--accent-start)" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 3px;">
                                    <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                                </svg>
                                <p style="margin: 0;">{{ $c->score_rationale }}</p>
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

</div>
