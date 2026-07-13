<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 28px;">
    <!-- Page Header -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub" style="margin-bottom:0;">Unggah video panjang Anda untuk dipotong secara cerdas menjadi klip-klip vertical viral oleh AI.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue" style="box-shadow: 0 0 12px rgba(59, 130, 246, 0.25);">
                <span class="spin"></span>&nbsp;Memproses Queue
            </span>
        @endif
    </div>

    <!-- System Status & Control Bar -->
    <div class="panel" style="padding: 14px 20px; background: rgba(20,20,25,0.6); border: 1px solid var(--border); border-radius: 12px; backdrop-filter: blur(8px);">
        <div class="row between" style="gap: 16px; flex-wrap: wrap;">
            <!-- Left: Status Indicators -->
            <div class="row" style="gap: 20px; flex-wrap: wrap;">
                <div style="font-family: 'Outfit', sans-serif; font-size: 13px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.5px;">
                    Sistem Status:
                </div>
                
                <!-- Whisper -->
                <div class="row" style="gap: 8px; font-size: 13px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['whisper'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['whisper'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span class="muted">Whisper:</span>
                    <strong style="color: {{ ($statuses['whisper'] ?? false) ? 'var(--text)' : '#ef4444' }};">{{ ($statuses['whisper'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- Face Tracking -->
                <div class="row" style="gap: 8px; font-size: 13px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['face'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['face'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span class="muted">Face Tracker:</span>
                    <strong style="color: {{ ($statuses['face'] ?? false) ? 'var(--text)' : '#ef4444' }};">{{ ($statuses['face'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- LLM -->
                <div class="row" style="gap: 8px; font-size: 13px;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['llm'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['llm'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span class="muted">LLM Scorer ({{ ucfirst($statuses['llm_driver'] ?? 'ollama') }}):</span>
                    <strong style="color: {{ ($statuses['llm'] ?? false) ? 'var(--text)' : '#ef4444' }};">{{ ($statuses['llm'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>
            </div>

            <!-- Right: Restart Actions -->
            <div class="row" style="gap: 12px;">
                <button type="button" wire:click="restartQueue" class="btn btn-sm btn-outline" style="border-color: rgba(255,255,255,0.08); background: rgba(255,255,255,0.02); display: flex; align-items: center; gap: 6px;" wire:loading.attr="disabled">
                    <svg xmlns="http://www.w3.org/2000/svg" width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" wire:loading.class="spin" wire:target="restartQueue" style="flex-shrink:0;">
                        <path d="M21.5 2v6h-6M21.34 15.57a10 10 0 1 1-.57-8.38l5.67-5.67"/>
                    </svg>
                    <span>Restart Antrean (.env)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Upload & URL Panels Grid -->
    <div class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: stretch;">
        
        <!-- Upload panel -->
        <div class="panel grid" style="gap: 16px; content-visibility: auto;">
            <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: -0.2px;">Upload File Video</h3>
            
            @if($flash)
                <div class="flash">{{ $flash }}</div>
            @endif
            @if($uploadError)
                <div class="flash flash-error">{{ $uploadError }}</div>
            @endif

            <form wire:submit="save" class="grid" style="gap: 18px; justify-content: space-between; height: 100%;">
                <!-- Styled Upload Zone -->
                <div style="border: 2px dashed var(--border); border-radius: 12px; padding: 28px 20px; text-align: center; background: rgba(255,255,255,0.01); transition: border-color 0.2s ease;"
                     ondragover="this.style.borderColor='var(--accent-start)'"
                     ondragleave="this.style.borderColor='var(--border)'">
                    <svg xmlns="http://www.w3.org/2000/svg" width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" style="color: var(--accent-start); margin-bottom: 12px; filter: drop-shadow(0 0 8px rgba(129,140,248,0.3));">
                        <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/>
                        <polyline points="17 8 12 3 7 8"/>
                        <line x1="12" y1="3" x2="12" y2="15"/>
                    </svg>
                    <div style="margin-bottom: 8px; font-weight: 600; color: var(--text);">Pilih atau seret file video di sini</div>
                    <div class="muted" style="font-size: 12px; margin-bottom: 14px;">Mendukung MP4, MOV, MKV, WebM, AVI (Maks. 3 Jam)</div>
                    
                    <input type="file" wire:model="upload" id="video-upload-input"
                           accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo"
                           style="display: none;">
                    <button type="button" class="btn btn-sm" onclick="document.getElementById('video-upload-input').click()">
                        Pilih Berkas
                    </button>
                    
                    @if($upload)
                        <div style="margin-top: 12px; color: var(--green); font-size: 12px; font-weight: 600;" wire:loading.remove wire:target="upload">
                            ✓ Berkas siap: {{ $upload->getClientOriginalName() }}
                        </div>
                    @endif
                </div>

                @error('upload') <span class="flash flash-error" style="padding: 8px 12px; margin-bottom: 0; font-size: 12px;">{{ $message }}</span> @enderror

                <div class="row between" style="gap: 12px; margin-top: auto;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;"
                            wire:loading.attr="disabled" wire:target="save,upload">
                        <span wire:loading.remove wire:target="save">Mulai Ingest &amp; Proses</span>
                        <span wire:loading wire:target="save"><span class="spin"></span>&nbsp;Mengunggah&hellip;</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- URL Ingest Panel -->
        <div class="panel grid" style="gap: 16px; content-visibility: auto;">
            <h3 style="margin: 0; font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: -0.2px;">Ambil Video dari URL</h3>
            
            <form wire:submit="ingestUrl" class="grid" style="gap: 20px; justify-content: space-between; height: 100%;">
                <div class="grid" style="gap: 12px;">
                    <label class="muted" style="font-weight: 600; font-size: 13px;">Tempel URL Video (YouTube, TikTok, dll)</label>
                    <input type="url" wire:model="url" placeholder="https://www.youtube.com/watch?v=..."
                           style="width: 100%; padding: 12px 16px; border-radius: 10px; background: var(--panel-2);
                                  border: 1px solid var(--border); color: var(--text); font-family: inherit; font-size: 14px;">
                    @if($urlError)
                        <span class="flash flash-error" style="padding: 8px 12px; margin-bottom: 0; font-size: 12px;">{{ $urlError }}</span>
                    @endif
                    
                    <div style="border: 1px solid rgba(59, 130, 246, 0.15); border-radius: 10px; padding: 16px; background: rgba(59, 130, 246, 0.03); display: flex; gap: 12px; align-items: flex-start; margin-top: 8px;">
                        <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#60a5fa" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 1px;">
                            <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                        </svg>
                        <p class="muted" style="font-size: 12px; line-height: 1.5; margin: 0;">
                            Unduhan diproses di latar belakang menggunakan `yt-dlp`. Video akan secara otomatis di-ingest, dikompresi, dan masuk ke pipeline transkripsi setelah selesai.
                        </p>
                    </div>
                </div>

                <div class="row" style="margin-top: auto;">
                    <button type="submit" class="btn btn-primary" style="width: 100%;"
                            wire:loading.attr="disabled" wire:target="ingestUrl">
                        <span wire:loading.remove wire:target="ingestUrl">Unduh &amp; Proses</span>
                        <span wire:loading wire:target="ingestUrl"><span class="spin"></span>&nbsp;Menghubungi Server&hellip;</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Video list -->
    <div class="panel">
        <div class="row between" style="margin-bottom: 18px;">
            <strong style="font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: -0.2px;">Daftar Video Projek ({{ $videos->count() }})</strong>
        </div>

        @if($videos->isEmpty())
            <div class="empty">Belum ada video projek yang di-ingest. Silakan upload file atau tautkan URL di atas.</div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:52px; text-align: center;">#</th>
                            <th>Detail Sumber</th>
                            <th style="width:100px;">Durasi</th>
                            <th style="width:340px;">Status Tahapan Pipeline</th>
                            <th style="width:90px; text-align: center;">Klip</th>
                            <th style="width:130px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($videos as $video)
                            <tr wire:key="video-{{ $video->id }}">
                                <td style="text-align: center;" class="muted font-semibold">{{ $video->id }}</td>
                                <td>
                                    <div style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight: 600; color: var(--text);">
                                        {{ $video->source_ref ?? '—' }}
                                    </div>
                                    <span class="muted" style="font-size:11px; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        @if($video->source_type === 'url')
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M10 13a5 5 0 0 0 7.54.54l3-3a5 5 0 0 0-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 0 0-7.54-.54l-3 3a5 5 0 0 0 7.07 7.07l1.71-1.71"/></svg>
                                        @else
                                            <svg xmlns="http://www.w3.org/2000/svg" width="10" height="10" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="17 8 12 3 7 8"/><line x1="12" y1="3" x2="12" y2="15"/></svg>
                                        @endif
                                        {{ strtoupper($video->source_type) }}
                                    </span>
                                </td>
                                <td class="muted font-semibold">
                                    @if($video->duration_seconds)
                                        {{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}
                                    @else — @endif
                                </td>
                                <td>
                                    <div class="row" style="gap:6px; flex-wrap: wrap;">
                                        @foreach($video->stageProgress() as $stage)
                                            @php
                                                $cls = match($stage['state']) {
                                                    'done' => 'badge-green',
                                                    'active' => 'badge-blue',
                                                    'failed' => 'badge-red',
                                                    default => 'badge-gray',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }}" style="font-size:10px; padding:3px 10px;"
                                                  title="{{ $stage['label'] }}: {{ $stage['state'] }}">
                                                @if($stage['state']==='active')<span class="spin" style="width:8px;height:8px;border-width:1.5px;margin-right:2px;"></span>@endif
                                                {{ $stage['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    @if($video->clip_candidates_count > 0)
                                        <span class="badge badge-blue" style="font-size: 11px; font-weight: 700; padding: 2px 8px;">{{ $video->clip_candidates_count }} Klip</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    @if(in_array($video->status, ['reviewing','done']))
                                        <a href="/videos/{{ $video->id }}/review" class="btn btn-sm btn-primary" style="padding: 5px 12px;">Review Hasil</a>
                                    @elseif($video->status === 'failed')
                                        @php $reason = $video->failureReason(); @endphp
                                        <span class="badge badge-red" @if($reason) title="{{ $reason }}" @endif style="cursor: help;">Gagal</span>
                                        @if($reason)
                                            <div class="muted" style="font-size:10px; margin-top:4px; max-width:140px;
                                                 overflow:hidden; text-overflow:ellipsis; white-space:nowrap; text-align: right;"
                                                 title="{{ $reason }}">
                                                {{ $reason }}
                                            </div>
                                        @endif
                                    @else
                                        <span class="muted" style="font-size:12px; display: inline-flex; align-items: center; gap: 6px;">
                                            <span class="spin" style="width:10px;height:10px;border-width:1.5px;"></span> Proses pipeline&hellip;
                                        </span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

    <!-- Live pipeline activity timeline -->
    <div>
        <livewire:activity-feed />
    </div>
</div>

