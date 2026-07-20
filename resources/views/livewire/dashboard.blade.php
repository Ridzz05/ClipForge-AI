<div @if($poll) wire:poll.2s @endif class="grid" style="gap: 28px;">

    <!-- Page Header (Purple Admin Style) -->
    <div class="row between" style="align-items: center; margin-bottom: 4px;">
        <div style="display: flex; align-items: center; gap: 10px;">
            <div style="width: 34px; height: 34px; border-radius: 8px; background: var(--purple-gradient); display: flex; align-items: center; justify-content: center; color: white; box-shadow: 0 4px 12px rgba(154, 85, 255, 0.3);">
                <i class="ph ph-house" style="font-size: 18px;"></i>
            </div>
            <h1 class="page-title">Dashboard</h1>
        </div>
        <div style="display: flex; align-items: center; gap: 12px;">
            <span style="font-size: 12.5px; color: var(--text-muted); font-weight: 600; cursor: pointer;">Overview <i class="ph ph-info" style="font-size: 14px; vertical-align: middle;"></i></span>
            @if($poll)
                <span class="badge badge-purple" style="padding: 6px 14px; font-size: 11px;">
                    <i class="ph ph-spinner-gap spin-rotate"></i>&nbsp;Memproses Queue
                </span>
            @endif
        </div>
    </div>


    <!-- Purple Admin Stat Cards (3 Gradient Cards Reference) -->
    <div class="grid" style="grid-template-columns: repeat(auto-fit, minmax(280px, 1fr)); gap: 24px;">
        <x-stat-card 
            title="Total Video Ingested" 
            :value="$videos->count()" 
            subtitle="Video sumber dalam sistem" 
            icon="video-camera" 
            gradient="coral" 
        />

        <x-stat-card 
            title="Kandidat Klip AI" 
            :value="\App\Models\ClipCandidate::count()" 
            subtitle="Hook viral terdeteksi oleh LLM" 
            icon="scissors" 
            gradient="blue" 
        />

        <x-stat-card 
            title="Hasil Ekspor Completed" 
            :value="\App\Models\Export::where('status', 'completed')->count()" 
            subtitle="Klip vertical (9:16) siap unduh" 
            icon="download-simple" 
            gradient="teal" 
        />
    </div>


    <!-- System Status & Control Bar (Purple Admin Style) -->
    <div class="panel" style="padding: 20px 24px;">
        <div class="row between" style="gap: 16px; flex-wrap: wrap;">
            <!-- Status Indicators -->
            <div class="row" style="gap: 20px; flex-wrap: wrap;">
                <div style="font-size: 11px; font-weight: 700; color: var(--text-muted); text-transform: uppercase; letter-spacing: 0.1em;">
                    System Status:
                </div>
                
                <!-- Whisper -->
                <div class="row" style="gap: 8px; font-size: 12.5px; font-weight: 600;">
                    <span style="width: 9px; height: 9px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['whisper'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 10px {{ ($statuses['whisper'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--text-muted)">Whisper:</span>
                    <strong style="color: {{ ($statuses['whisper'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['whisper'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- Face Tracking -->
                <div class="row" style="gap: 8px; font-size: 12.5px; font-weight: 600;">
                    <span style="width: 9px; height: 9px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['face'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 10px {{ ($statuses['face'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--text-muted)">Face Tracker:</span>
                    <strong style="color: {{ ($statuses['face'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['face'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- LLM -->
                <div class="row" style="gap: 8px; font-size: 12.5px; font-weight: 600;">
                    <span style="width: 9px; height: 9px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['llm'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 10px {{ ($statuses['llm'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--text-muted)">LLM ({{ ucfirst($statuses['llm_driver'] ?? 'ollama') }}):</span>
                    <strong style="color: {{ ($statuses['llm'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['llm'] ?? false) ? 'Online' : 'Offline' }}</strong>
                </div>

                <!-- Queue Worker -->
                <div class="row" style="gap: 8px; font-size: 12.5px; font-weight: 600;">
                    <span style="width: 9px; height: 9px; border-radius: 50%; display: inline-block; background-color: {{ ($statuses['queue'] ?? false) ? '#10b981' : '#ef4444' }}; box-shadow: 0 0 10px {{ ($statuses['queue'] ?? false) ? 'rgba(16,185,129,0.5)' : 'rgba(239,68,68,0.5)' }};"></span>
                    <span style="color: var(--text-muted)">Queue Worker:</span>
                    <strong style="color: {{ ($statuses['queue'] ?? false) ? 'var(--text-title)' : '#ef4444' }};">{{ ($statuses['queue'] ?? false) ? 'Running' : 'Stopped' }}</strong>
                </div>
            </div>

            <!-- Control Actions -->
            <div class="row" style="gap: 10px;">
                @if(!($statuses['queue'] ?? false))
                    <button type="button" wire:click="wakeUpQueue" class="btn btn-sm" style="background: #f59e0b; box-shadow: 0 4px 12px rgba(245,158,11,0.3);" wire:loading.attr="disabled">
                        <i class="ph ph-lightning" wire:loading.class="spin-rotate" wire:target="wakeUpQueue"></i>
                        <span>Bangunkan Antrean</span>
                    </button>
                @endif
                <button type="button" wire:click="restartQueue" class="btn btn-sm btn-outline" wire:loading.attr="disabled">
                    <i class="ph ph-arrows-clockwise" wire:loading.class="spin-rotate" wire:target="restartQueue"></i>
                    <span>Restart Queue (.env)</span>
                </button>
            </div>
        </div>
    </div>

    <!-- Upload & URL Panels Grid -->
    <div id="upload-section" class="grid" style="grid-template-columns: repeat(auto-fit, minmax(340px, 1fr)); gap: 24px;">
        
        <!-- File Upload Panel -->
        <div class="panel grid" style="gap: 20px;">
            <h3 style="margin: 0; font-family: var(--font-title); font-size: 20px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px;">
                <i class="ph ph-cloud-arrow-up" style="color: var(--purple-primary); font-size: 24px;"></i>Upload File Video
            </h3>
            
            @if($uploadError)
                <div style="padding: 12px 16px; background: rgba(239, 68, 68, 0.1); border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 12px; color: #ef4444; font-size: 13px; font-weight: 600;">
                    {{ $uploadError }}
                </div>
            @endif

            <form wire:submit="save" class="grid" style="gap: 18px;">
                <!-- Drag & Drop Zone -->
                <div x-data="{ isUploading: false, progress: 0 }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:livewire-upload-progress="progress = $event.detail.progress"
                     style="border: 2px dashed var(--border-purple); border-radius: 18px; padding: 28px 20px; text-align: center; background: var(--bg-surface-subtle); transition: all 0.2s ease;">
                    
                    <div x-show="!isUploading">
                        <i class="ph ph-video-camera" style="font-size: 38px; color: var(--purple-primary); margin-bottom: 10px; display: inline-block;"></i>
                        <div style="font-weight: 700; color: var(--text-title); font-size: 14px; margin-bottom: 4px;">Pilih atau seret berkas video di sini</div>
                        <div style="font-size: 12px; color: var(--text-muted); margin-bottom: 16px;">Format: MP4, MOV, MKV, WebM, AVI (Maks. 3 Jam)</div>
                        
                        <input type="file" wire:model="upload" id="video-upload-input"
                               accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo"
                               style="display: none;">
                        <button type="button" class="btn btn-sm btn-outline" onclick="document.getElementById('video-upload-input').click()">
                            Pilih Berkas Video
                        </button>
                    </div>

                    <!-- Progress Bar -->
                    <div x-show="isUploading" style="padding: 10px 0; display: none;">
                        <i class="ph ph-spinner-gap spin-rotate" style="font-size: 32px; color: var(--purple-primary); margin-bottom: 10px; display: inline-block;"></i>
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 8px;">
                            <span style="font-size: 13px; font-weight: 700; color: var(--text-title);">Sedang mengunggah ke server...</span>
                            <span x-text="progress + '%'" style="font-size: 13px; font-weight: 700; color: var(--purple-primary);">0%</span>
                        </div>
                        <div style="width: 100%; height: 8px; background: rgba(154, 85, 255, 0.15); border-radius: 99px; overflow: hidden;">
                            <div :style="`width: ${progress}%; transition: width 0.1s ease;`" style="height: 100%; background: var(--purple-gradient); border-radius: 99px;"></div>
                        </div>
                    </div>
                    
                    @if($upload)
                        <div style="margin-top: 14px; color: #10b981; font-size: 12.5px; font-weight: 700;" x-show="!isUploading">
                            <i class="ph ph-check-circle" style="vertical-align: middle; font-size: 16px;"></i> Berkas siap: {{ $upload->getClientOriginalName() }}
                        </div>
                    @endif
                </div>

                @error('upload')
                    <span style="color: #ef4444; font-size: 12px; font-weight: 600;">{{ $message }}</span>
                @enderror

                <!-- Auto-Clip Checkbox Option -->
                <div style="display: flex; align-items: flex-start; gap: 12px; background: var(--bg-surface-subtle); padding: 14px; border-radius: 14px; border: 1px solid var(--border-color);">
                    <input type="checkbox" wire:model="autoClip" id="auto-clip-upload" style="margin-top: 3px; cursor: pointer; accent-color: var(--purple-primary); transform: scale(1.2);">
                    <label for="auto-clip-upload" style="font-size: 12.5px; color: var(--text-main); line-height: 1.4; cursor: pointer; text-align: left;">
                        <strong style="display: block; margin-bottom: 2px; color: var(--text-title);">Render &amp; Ekspor Otomatis (Auto-Clip)</strong>
                        Langsung memotong 9:16 untuk 3 klip terbaik (Skor &ge; 75). Nonaktifkan jika hanya ingin mereview klip terlebih dahulu.
                    </label>
                </div>

                <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save,upload">
                    <span wire:loading.remove wire:target="save">Mulai Ingest &amp; Process Video</span>
                    <span wire:loading wire:target="save"><i class="ph ph-spinner-gap spin-rotate"></i>&nbsp;Mengunggah&hellip;</span>
                </button>
            </form>
        </div>

        <!-- URL Import Panel -->
        <div class="panel grid" style="gap: 20px;">
            <h3 style="margin: 0; font-family: var(--font-title); font-size: 20px; font-weight: 800; color: var(--text-title); display: flex; align-items: center; gap: 8px;">
                <i class="ph ph-link" style="color: var(--purple-primary); font-size: 24px;"></i>Import Video dari URL
            </h3>
            
            <form wire:submit="ingestUrl" class="grid" style="gap: 18px;">
                <div class="grid" style="gap: 12px;">
                    <label style="font-weight: 700; font-size: 13px; color: var(--text-title);">Tempel Tautan URL (YouTube, TikTok, dll)</label>
                    <input type="url" wire:model="url" placeholder="https://www.youtube.com/watch?v=...">
                    
                    @if($urlError)
                        <span style="color: #ef4444; font-size: 12px; font-weight: 600;">{{ $urlError }}</span>
                    @endif

                    <div style="margin-top: 4px;">
                        <label style="font-weight: 700; font-size: 13px; color: var(--text-title); display: block; margin-bottom: 6px;">Batas Resolusi Unduhan</label>
                        <select wire:model="resolution"
                                style="width: 100%; padding: 12px 16px; border-radius: 14px; background: var(--bg-surface-subtle);
                                       border: 1.5px solid var(--border-color); color: var(--text-main); font-family: inherit; font-size: 13.5px; outline: none; cursor: pointer;">
                            <option value="best">Kualitas Terbaik (Best MP4)</option>
                            <option value="1080p">Maksimal 1080p</option>
                            <option value="720p">Maksimal 720p</option>
                            <option value="480p">Maksimal 480p (Hemat Bandwidth)</option>
                            <option value="360p">Maksimal 360p (Sangat Cepat)</option>
                        </select>
                    </div>
                </div>

                <!-- Auto-Clip Checkbox Option -->
                <div style="display: flex; align-items: flex-start; gap: 12px; background: var(--bg-surface-subtle); padding: 14px; border-radius: 14px; border: 1px solid var(--border-color);">
                    <input type="checkbox" wire:model="autoClip" id="auto-clip-url" style="margin-top: 3px; cursor: pointer; accent-color: var(--purple-primary); transform: scale(1.2);">
                    <label for="auto-clip-url" style="font-size: 12.5px; color: var(--text-main); line-height: 1.4; cursor: pointer; text-align: left;">
                        <strong style="display: block; margin-bottom: 2px; color: var(--text-title);">Render &amp; Ekspor Otomatis (Auto-Clip)</strong>
                        Langsung memotong 9:16 untuk 3 klip terbaik (Skor &ge; 75).
                    </label>
                </div>

                <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="ingestUrl">
                    <span wire:loading.remove wire:target="ingestUrl">Unduh &amp; Process Video</span>
                    <span wire:loading wire:target="ingestUrl"><i class="ph ph-spinner-gap spin-rotate"></i>&nbsp;Menghubungi Server&hellip;</span>
                </button>
            </form>
        </div>
    </div>

    <!-- Video Project List Table -->
    <div class="panel">
        <div class="row between" style="margin-bottom: 20px;">
            <h3 style="margin: 0; font-family: var(--font-title); font-size: 22px; font-weight: 800; color: var(--text-title);">
                Daftar Video Project ({{ $videos->count() }})
            </h3>
        </div>

        @if($videos->isEmpty())
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 14px;">
                <i class="ph ph-folder-open" style="font-size: 42px; color: var(--purple-primary); margin-bottom: 12px; display: block;"></i>
                Belum ada video project yang di-ingest. Silakan unggah file atau masukkan URL video di atas.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width: 50px; text-align: center;">#</th>
                            <th>Detail Sumber</th>
                            <th style="width: 100px;">Durasi</th>
                            <th style="width: 320px;">Status Pipeline</th>
                            <th style="width: 110px; text-align: center;">Kandidat</th>
                            <th style="width: 140px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($videos as $video)
                            <tr wire:key="video-{{ $video->id }}">
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);">{{ $video->id }}</td>
                                <td>
                                    <div style="max-width: 260px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; color: var(--text-title);">
                                        {{ $video->source_ref ?? '—' }}
                                    </div>
                                    <span style="font-size: 11px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        <i class="ph {{ $video->source_type === 'url' ? 'ph-link' : 'ph-file-video' }}"></i>
                                        {{ strtoupper($video->source_type) }}
                                    </span>
                                </td>
                                <td style="font-weight: 600; color: var(--text-main);">
                                    @if($video->duration_seconds)
                                        {{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}
                                    @else — @endif
                                </td>
                                <td>
                                    <div class="row" style="gap: 6px; flex-wrap: wrap; cursor: pointer;" wire:click="showStatusModal({{ $video->id }})" title="Klik untuk detail pipeline status">
                                        @foreach($video->stageProgress() as $stage)
                                            @php
                                                $cls = match($stage['state']) {
                                                    'done' => 'badge-green',
                                                    'active' => 'badge-purple',
                                                    'failed' => 'badge-red',
                                                    default => 'badge-amber',
                                                };
                                            @endphp
                                            <span class="badge {{ $cls }}" style="font-size: 10px; padding: 4px 10px;">
                                                @if($stage['state']==='active')<i class="ph ph-spinner-gap spin-rotate" style="font-size: 10px;"></i>@endif
                                                {{ $stage['label'] }}
                                            </span>
                                        @endforeach
                                    </div>
                                </td>
                                <td style="text-align: center;">
                                    @if($video->clip_candidates_count > 0)
                                        <span class="badge badge-purple">{{ $video->clip_candidates_count }} Klip</span>
                                    @else
                                        <span style="color: var(--text-muted);">—</span>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    <div style="display: flex; align-items: center; gap: 8px; justify-content: flex-end;">
                                        @if(in_array($video->status, ['reviewing','done']))
                                            <a href="/videos/{{ $video->id }}/review" wire:navigate class="btn btn-sm">
                                                <i class="ph ph-eye"></i> Review
                                            </a>
                                        @elseif($video->status === 'failed')
                                            @php $reason = $video->failureReason(); @endphp
                                            <div style="text-align: right;">
                                                <span class="badge badge-red" @if($reason) title="{{ $reason }}" @endif>Gagal</span>
                                                @if($reason)
                                                    <div style="font-size: 10px; color: #ef4444; margin-top: 2px; max-width: 140px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" title="{{ $reason }}">
                                                        {{ $reason }}
                                                    </div>
                                                @endif
                                            </div>

                                        @else
                                            <span style="font-size: 12px; color: var(--purple-primary); font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                                <i class="ph ph-spinner-gap spin-rotate"></i> Memproses
                                            </span>
                                        @endif

                                        <button type="button" wire:click="deleteVideo({{ $video->id }})"
                                                wire:confirm="Hapus video ini beserta kandidat & ekspor terkait secara permanen?"
                                                class="btn btn-sm btn-outline"
                                                style="padding: 6px 10px; border-color: rgba(239, 68, 68, 0.3); color: #ef4444;"
                                                title="Hapus Video">
                                            <i class="ph ph-trash"></i>
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

    <!-- Activity Timeline -->
    <div>
        <livewire:activity-feed />
    </div>

    <!-- Pipeline Status Modal -->
    @if($showStatusModal && $selectedVideo)
        <div style="position: fixed; top: 0; left: 0; right: 0; bottom: 0; background: rgba(0,0,0,0.6); backdrop-filter: blur(8px); z-index: 10000; display: flex; align-items: center; justify-content: center; padding: 20px;"
             wire:click.self="closeStatusModal">
            
            <div class="panel grid" style="width: 100%; max-width: 640px; max-height: 90vh; overflow-y: auto;">
                <div class="row between" style="border-bottom: 1px solid var(--border-color); padding-bottom: 16px;">
                    <div>
                        <h3 style="margin: 0; font-family: var(--font-title); font-size: 22px; font-weight: 800; color: var(--text-title);">
                            Detail Pipeline #{{ $selectedVideo->id }}
                        </h3>
                        <span style="font-size: 12px; color: var(--text-muted);">
                            Sumber: <strong style="font-family: var(--font-mono);">{{ $selectedVideo->source_ref }}</strong>
                        </span>
                    </div>
                    <button type="button" class="btn btn-sm btn-outline" wire:click="closeStatusModal">Tutup</button>
                </div>

                <div class="grid" style="gap: 16px;">
                    @foreach($selectedVideo->stageProgress() as $progress)
                        @php
                            $dbJob = $selectedVideo->pipelineJobs->firstWhere('stage', $progress['key']);
                            $state = $progress['state'];
                            $badgeCls = match($state) {
                                'done' => 'badge-green',
                                'active' => 'badge-purple',
                                'failed' => 'badge-red',
                                default => 'badge-amber',
                            };
                        @endphp

                        <div style="border: 1px solid var(--border-color); border-radius: 14px; padding: 16px; background: var(--bg-surface-subtle);">
                            <div class="row between" style="margin-bottom: 6px;">
                                <strong style="font-size: 14px; color: var(--text-title);">{{ $progress['label'] }}</strong>
                                <span class="badge {{ $badgeCls }}">{{ strtoupper($state) }}</span>
                            </div>
                            @if($dbJob && $dbJob->last_error)
                                <div style="margin-top: 8px; background: rgba(239,68,68,0.1); padding: 10px; border-radius: 8px; font-family: var(--font-mono); font-size: 11px; color: #ef4444;">
                                    {{ $dbJob->last_error }}
                                </div>
                            @endif
                        </div>
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
