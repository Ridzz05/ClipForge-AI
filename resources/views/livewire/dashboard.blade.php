<div @if($poll) wire:poll.2s @endif class="grid" style="gap: 32px;">
    <!-- Page Header -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub" style="margin-bottom:0;">Unggah video panjang Anda untuk dipotong secara cerdas menjadi klip-klip vertical viral oleh AI.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue" style="background: var(--tile-5); color: var(--ink); border: 1px solid rgba(0,0,0,0.06);">
                <i class="ph ph-spinner-gap spin-rotate" style="font-size: 14px;"></i>&nbsp;Memproses Queue
            </span>
        @endif
    </div>

    <!-- System Status & Control Bar -->
    <div class="panel" style="padding: 18px 24px; background: var(--stage-2); border: 1px dashed var(--border-stage); border-radius: 18px; color: var(--text-title);">
        <div class="row between" style="gap: 16px; flex-wrap: wrap;">
            <!-- Left: Status Indicators -->
            <div class="row" style="gap: 20px; flex-wrap: wrap;">
                <div style="font-family: var(--mono); font-size: 11px; font-weight: 700; color: var(--muted); text-transform: uppercase; letter-spacing: 0.15em;">
                    Sistem Status:
                </div>
                
                <!-- Whisper -->
                <div class="row" style="gap: 8px; font-size: 12px; font-family: var(--mono); letter-spacing: 0.05em;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['whisper'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['whisper'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--muted)">Whisper:</span>
                    <strong style="color: {{ ($statuses['whisper'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['whisper'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- Face Tracking -->
                <div class="row" style="gap: 8px; font-size: 12px; font-family: var(--mono); letter-spacing: 0.05em;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['face'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['face'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--muted)">Face Tracker:</span>
                    <strong style="color: {{ ($statuses['face'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['face'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- LLM -->
                <div class="row" style="gap: 8px; font-size: 12px; font-family: var(--mono); letter-spacing: 0.05em;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['llm'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['llm'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--muted)">LLM Scorer ({{ ucfirst($statuses['llm_driver'] ?? 'ollama') }}):</span>
                    <strong style="color: {{ ($statuses['llm'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['llm'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- Queue Worker -->
                <div class="row" style="gap: 8px; font-size: 12px; font-family: var(--mono); letter-spacing: 0.05em;">
                    <span style="width: 8px; height: 8px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['queue'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 8px {{ ($statuses['queue'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--muted)">Queue Worker (Antrean):</span>
                    <strong style="color: {{ ($statuses['queue'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['queue'] ?? false) ? 'Running' : 'Stopped' }}</strong>
                </div>
            </div>

            <!-- Right: Restart Actions -->
            <div class="row" style="gap: 12px; flex-wrap: wrap;">
                @if(!($statuses['queue'] ?? false))
                    <button type="button" wire:click="wakeUpQueue" class="btn btn-sm" style="padding: 6px 12px; background: #eab308; border: 1.5px solid #ca8a04; color: black; font-weight: 700; display: flex; align-items: center; gap: 6px; border-radius: 10px;" wire:loading.attr="disabled">
                        <i class="ph ph-lightning" wire:loading.class="spin-rotate" wire:target="wakeUpQueue" style="font-size: 14px;"></i>
                        <span>Bangunkan Antrean</span>
                    </button>
                @endif
                <button type="button" wire:click="restartQueue" class="btn btn-sm btn-outline" style="border-color: var(--border-stage); background: rgba(255,255,255,0.03); color: var(--text-title); display: flex; align-items: center; gap: 6px;" wire:loading.attr="disabled">
                    <i class="ph ph-arrows-clockwise" wire:loading.class="spin-rotate" wire:target="restartQueue" style="font-size: 14px;"></i>
                    <span>Restart Antrean (.env)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Upload & URL Panels Grid -->
    <div id="upload-section" class="grid" style="grid-template-columns: 1fr 1fr; gap: 24px; align-items: stretch;">
        
        <!-- Upload panel -->
        <div class="panel grid" style="gap: 16px; background: var(--tile-2); border-color: rgba(0,0,0,0.05); color: var(--ink);">
            <h3 style="margin: 0; font-family: var(--serif); font-style: italic; font-size: 24px; font-weight: 700; color: var(--ink);">
                <i class="ph ph-upload-simple" style="vertical-align: middle; margin-right: 4px;"></i>Upload File Video
            </h3>
            
            @if($uploadError)
                <div class="flash flash-error" style="background: var(--paper);">{{ $uploadError }}</div>
            @endif

            <form wire:submit="save" class="grid" style="gap: 18px; justify-content: space-between; height: 100%;">
                <!-- Styled Upload Zone -->
                <div style="border: 2px dashed var(--line); border-radius: 18px; padding: 28px 20px; text-align: center; background: rgba(0,0,0,0.03); transition: border-color 0.2s ease;"
                     ondragover="this.style.borderColor='var(--ink)'"
                     ondragleave="this.style.borderColor='var(--line)'">
                    <i class="ph ph-cloud-arrow-up" style="font-size: 32px; color: var(--ink); margin-bottom: 12px; display: inline-block;"></i>
                    <div style="margin-bottom: 8px; font-weight: 700; color: var(--ink); font-size: 14px;">Pilih atau seret file video di sini</div>
                    <div style="font-size: 12px; margin-bottom: 14px; color: var(--muted); font-weight: 500;">Mendukung MP4, MOV, MKV, WebM, AVI (Maks. 3 Jam)</div>
                    
                    <input type="file" wire:model="upload" id="video-upload-input"
                           accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo"
                           style="display: none;">
                    <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('video-upload-input').click()">
                        Pilih Berkas
                    </button>
                    
                    @if($upload)
                        <div style="margin-top: 12px; color: #15803d; font-size: 12px; font-weight: 700;" wire:loading.remove wire:target="upload">
                            <i class="ph ph-check-circle" style="vertical-align: middle;"></i> Berkas siap: {{ $upload->getClientOriginalName() }}
                        </div>
                    @endif
                </div>

                @error('upload') <span class="flash flash-error" style="padding: 8px 12px; margin-bottom: 0; font-size: 12px; background: var(--paper);">{{ $message }}</span> @enderror

                <div class="row between" style="gap: 12px; margin-top: auto;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; border-color: var(--ink); background: var(--ink); color: var(--paper);"
                            wire:loading.attr="disabled" wire:target="save,upload">
                        <span wire:loading.remove wire:target="save">Mulai Ingest &amp; Proses</span>
                        <span wire:loading wire:target="save"><i class="ph ph-spinner-gap spin-rotate" style="font-size:14px;"></i>&nbsp;Mengunggah&hellip;</span>
                    </button>
                </div>
            </form>
        </div>

        <!-- URL Ingest Panel -->
        <div class="panel grid" style="gap: 16px; background: var(--tile-1); border-color: rgba(0,0,0,0.05); color: var(--ink);">
            <h3 style="margin: 0; font-family: var(--serif); font-style: italic; font-size: 24px; font-weight: 700; color: var(--ink);">
                <i class="ph ph-link" style="vertical-align: middle; margin-right: 4px;"></i>Ambil Video dari URL
            </h3>
            
            <form wire:submit="ingestUrl" class="grid" style="gap: 20px; justify-content: space-between; height: 100%;">
                <div class="grid" style="gap: 12px;">
                    <label style="font-weight: 700; font-size: 13px; color: var(--ink);">Tempel URL Video (YouTube, TikTok, dll)</label>
                    <input type="url" wire:model="url" placeholder="https://www.youtube.com/watch?v=..."
                           style="width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--paper);
                                  border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;">
                    @if($urlError)
                        <span class="flash flash-error" style="padding: 8px 12px; margin-bottom: 0; font-size: 12px; background: var(--paper);">{{ $urlError }}</span>
                    @endif

                    <div style="margin-top: 4px;">
                        <label style="font-weight: 700; font-size: 13px; color: var(--ink); display: block; margin-bottom: 6px;">Batas Resolusi Unduhan</label>
                        <select wire:model="resolution"
                                style="width: 100%; padding: 12px 16px; border-radius: 12px; background: var(--paper);
                                       border: 1.5px solid var(--line); color: var(--ink); font-family: inherit; font-size: 13.5px;
                                       outline: none; transition: border-color 0.2s ease; cursor: pointer;">
                            <option value="best">Kualitas Terbaik (Best MP4)</option>
                            <option value="1080p">Maksimal 1080p</option>
                            <option value="720p">Maksimal 720p</option>
                            <option value="480p">Maksimal 480p (Hemat Bandwidth)</option>
                            <option value="360p">Maksimal 360p (Sangat Cepat)</option>
                        </select>
                    </div>
                    
                    <div style="border: 1.5px dashed var(--line); border-radius: 12px; padding: 16px; background: rgba(0,0,0,0.03); display: flex; gap: 12px; align-items: flex-start; margin-top: 8px;">
                        <i class="ph ph-info" style="font-size: 18px; flex-shrink: 0; color: var(--ink); margin-top: 1px;"></i>
                        <p style="font-size: 12px; line-height: 1.5; margin: 0; color: var(--muted); font-weight: 500;">
                            Unduhan diproses di latar belakang menggunakan `yt-dlp`. Video akan secara otomatis di-ingest, dikompresi, dan masuk ke pipeline transkripsi setelah selesai.
                        </p>
                    </div>
                </div>

                <div class="row" style="margin-top: auto;">
                    <button type="submit" class="btn btn-primary" style="width: 100%; border-color: var(--ink); background: var(--ink); color: var(--paper);"
                            wire:loading.attr="disabled" wire:target="ingestUrl">
                        <span wire:loading.remove wire:target="ingestUrl">Unduh &amp; Proses</span>
                        <span wire:loading wire:target="ingestUrl"><i class="ph ph-spinner-gap spin-rotate" style="font-size:14px;"></i>&nbsp;Menghubungi Server&hellip;</span>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Video list -->
    <div class="panel">
        <div class="row between" style="margin-bottom: 20px;">
            <strong style="font-family: var(--serif); font-style: italic; font-size: 26px; font-weight: 700; color: var(--ink);">Daftar Video Projek ({{ $videos->count() }})</strong>
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
                            <th style="width:110px; text-align: center;">Klip</th>
                            <th style="width:130px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($videos as $video)
                            <tr wire:key="video-{{ $video->id }}">
                                <td style="text-align: center;" class="muted font-semibold">{{ $video->id }}</td>
                                <td>
                                    <div style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight: 700; color: var(--ink);">
                                        {{ $video->source_ref ?? '—' }}
                                    </div>
                                    <span class="muted" style="font-size:11px; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        @if($video->source_type === 'url')
                                            <i class="ph ph-link" style="font-size: 11px;"></i>
                                        @else
                                            <i class="ph ph-file-video" style="font-size: 11px;"></i>
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
                                    <div class="row" style="gap:6px; flex-wrap: wrap; cursor: pointer;" wire:click="showStatusModal({{ $video->id }})" title="Klik untuk detail pipeline status">
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
                                                @if($stage['state']==='active')<i class="ph ph-spinner-gap spin-rotate" style="font-size:10px; margin-right:2px;"></i>@endif
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
                                    <div style="display: inline-flex; align-items: center; gap: 6px; justify-content: flex-end; width: 100%;">
                                        @if(in_array($video->status, ['reviewing','done']))
                                            <a href="/videos/{{ $video->id }}/review" wire:navigate class="btn btn-sm btn-primary" style="padding: 5px 12px; border-color: var(--ink); background: var(--ink); color: var(--paper);">
                                                <i class="ph ph-eye" style="font-size: 12px; vertical-align: middle;"></i> Review
                                            </a>
                                        @elseif($video->status === 'failed')
                                            @php $reason = $video->failureReason(); @endphp
                                            <span class="badge badge-red" @if($reason) title="{{ $reason }}" @endif style="cursor: help;">Gagal</span>
                                            @if($reason)
                                                <div class="muted" style="font-size:10px; max-width:140px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $reason }}">
                                                    {{ $reason }}
                                                </div>
                                            @endif
                                        @else
                                            <span class="muted" style="font-size:12px; display: inline-flex; align-items: center; gap: 4px;" title="Status: {{ $video->status }}">
                                                <i class="ph ph-spinner-gap spin-rotate" style="font-size: 12px;"></i>
                                                @if(str_starts_with($video->status, 'downloading'))
                                                    @if(preg_match('/downloading\s+\(([^)]+)\)/i', $video->status, $matches))
                                                        Download {{ $matches[1] }}
                                                    @else
                                                        Downloading...
                                                    @endif
                                                @else
                                                    Proses...
                                                @endif
                                            </span>
                                        @endif

                                        <button type="button" wire:click="deleteVideo({{ $video->id }})"
                                                wire:confirm="Apakah Anda yakin ingin menghapus video ini beserta seluruh data kandidat & hasil ekspor terkait secara permanen?"
                                                class="btn btn-sm btn-outline"
                                                style="padding: 5px 8px; border-color: #dc2626; color: #dc2626; border-radius: 8px;"
                                                title="Hapus Video">
                                            <i class="ph ph-trash" style="font-size: 14px; vertical-align: middle;"></i>
                                        </button>
                                    </div>
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

    <!-- Pipeline Status Modal Overlay -->
    @if($showStatusModal && $selectedVideo)
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); -webkit-backdrop-filter: blur(8px); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;"
             wire:click.self="closeStatusModal">
            
            <div class="panel grid" style="width: 100%; max-width: 640px; background: var(--stage-2); border: 1.5px solid var(--line); border-radius: 20px; box-shadow: 0 20px 50px rgba(0,0,0,0.3); overflow: hidden; padding: 24px; gap: 20px; color: var(--ink); animation: modalFadeIn 0.2s ease;">
                <style>
                    @keyframes modalFadeIn {
                        from { opacity: 0; transform: scale(0.96); }
                        to { opacity: 1; transform: scale(1); }
                    }
                </style>
                <!-- Header -->
                <div class="row between" style="border-bottom: 1.5px solid var(--line); padding-bottom: 14px; align-items: center; gap: 12px;">
                    <div style="display: flex; flex-direction: column; gap: 4px; min-width: 0;">
                        <h3 style="margin: 0; font-family: var(--serif); font-style: italic; font-size: 24px; font-weight: 700; color: var(--ink); white-space: nowrap; overflow: hidden; text-overflow: ellipsis;">
                            <i class="ph ph-activity" style="vertical-align: middle; margin-right: 4px;"></i>Detail Pipeline #{{ $selectedVideo->id }}
                        </h3>
                        <span style="font-size: 12px; color: var(--muted); font-weight: 500; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; display: block;">
                            Sumber: <strong style="font-family: var(--mono); font-size:11px;">{{ $selectedVideo->source_ref }}</strong>
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" wire:click="closeStatusModal" style="padding: 6px 12px; border-radius: 8px; border-color: var(--line); color: var(--ink); flex-shrink: 0;">
                        Tutup
                    </button>
                </div>

                <!-- Stages Timeline -->
                <div class="grid" style="gap: 16px; max-height: 60vh; overflow-y: auto; padding-right: 4px;">
                    @php
                        $stageDetails = [
                            'ingest' => [
                                'title' => '1. Ingest (Unduh & Validasi)',
                                'desc' => 'Mengunduh video dari URL (menggunakan yt-dlp dengan persentase real-time) atau memproses unggahan berkas video lokal, serta memverifikasi kesesuaian durasi dan bytes data.'
                            ],
                            'transcribe' => [
                                'title' => '2. Transcribe (Deteksi Suara ke Teks)',
                                'desc' => 'Mengekstrak audio dan menganalisis frekuensi suara menggunakan AI faster-whisper untuk menghasilkan teks transkripsi kata-demi-kata dengan stempel waktu.'
                            ],
                            'score' => [
                                'title' => '3. Score (Rekomendasi Klip AI)',
                                'desc' => 'Mengirim teks transkripsi ke model LLM lokal (Ollama) untuk mencari hook viral, menilai skor kelayakan (0-100%), dan menyusun ringkasan deskripsi klip.'
                            ],
                            'review' => [
                                'title' => '4. Review & Konfigurasi',
                                'desc' => 'Operator (Anda) melakukan validasi waktu potong, menulis teks Call-To-Action (CTA), memilih gaya tulisan subtitel, dan menyetujui klip untuk dirender.'
                            ]
                        ];
                    @endphp

                    @foreach($selectedVideo->stageProgress() as $progress)
                        @php
                            $detail = $stageDetails[$progress['key']] ?? ['title' => $progress['label'], 'desc' => ''];
                            
                            // Find the corresponding database record to display details
                            $dbJob = $selectedVideo->pipelineJobs->firstWhere('stage', $progress['key']);
                            $state = $progress['state']; // done|active|failed|pending
                            
                            $badgeCls = match($state) {
                                'done' => 'badge-green',
                                'active' => 'badge-blue',
                                'failed' => 'badge-red',
                                default => 'badge-gray',
                            };
                            
                            $badgeLabel = match($state) {
                                'done' => 'Selesai',
                                'active' => 'Berjalan',
                                'failed' => 'Gagal',
                                default => 'Menunggu',
                            };
                        @endphp

                        <div style="border: 1.5px solid var(--line); border-radius: 12px; padding: 14px; background: rgba(0,0,0,0.02); display: flex; flex-direction: column; gap: 8px;">
                            <div class="row between" style="align-items: center; width: 100%; flex-direction: row !important; gap: 8px;">
                                <strong style="font-size: 14px; font-weight: 700; color: var(--ink);">{{ $detail['title'] }}</strong>
                                <span class="badge {{ $badgeCls }}" style="font-size: 10px; padding: 3px 10px; font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                    @if($state === 'active')<i class="ph ph-spinner-gap spin-rotate" style="font-size: 10px;"></i>@endif
                                    {{ $badgeLabel }}
                                </span>
                            </div>
                            
                            <p style="margin: 0; font-size: 12px; line-height: 1.5; color: var(--muted); font-weight: 500;">
                                {{ $detail['desc'] }}
                            </p>
                            
                            @if($dbJob)
                                <div class="row" style="gap: 12px; font-family: var(--mono); font-size: 10px; color: var(--muted); margin-top: 4px; flex-direction: row !important;">
                                    <span>Percobaan: {{ $dbJob->attempts }}</span>
                                    <span>•</span>
                                    <span>Mulai: {{ $dbJob->created_at->setTimezone('Asia/Jakarta')->format('H:i:s') }}</span>
                                    @if($dbJob->status !== 'running' && $dbJob->status !== 'queued')
                                        <span>•</span>
                                        <span>Selesai: {{ $dbJob->updated_at->setTimezone('Asia/Jakarta')->format('H:i:s') }}</span>
                                    @endif
                                </div>
                                
                                @if($dbJob->last_error)
                                    <div style="margin-top: 8px; background: rgba(239, 68, 68, 0.05); border: 1.5px solid rgba(239, 68, 68, 0.2); border-radius: 8px; padding: 10px 14px; font-family: var(--mono); font-size: 11px; color: #f87171; overflow-x: auto; line-height: 1.5;">
                                        <strong>Log Error:</strong> {{ $dbJob->last_error }}
                                    </div>
                                @endif
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
