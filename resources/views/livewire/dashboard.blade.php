<div @if($poll) wire:poll.2s @endif class="dash-page">
<style>
    .dash-page { display: flex; flex-direction: column; gap: var(--s-6); }

    .dash-paths { color: var(--graphite); font-size: 0.875rem; }
    .dash-paths a { white-space: nowrap; }

    .dash-ingest {
        display: grid;
        grid-template-columns: minmax(0, 1.55fr) minmax(0, 1fr);
        gap: var(--s-5);
        align-items: start;
    }
    @media (max-width: 900px) {
        .dash-ingest { grid-template-columns: minmax(0, 1fr); }
    }

    .dash-status-list {
        display: grid; grid-template-columns: repeat(auto-fit, minmax(min(100%, 220px), 1fr));
        gap: var(--s-3) var(--s-5); margin: 0; padding: 0; list-style: none;
    }
    .dash-status-item { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--s-2); font-size: 0.875rem; min-width: 0; }
    .dash-status-name { color: var(--muted-ink); }
    .dash-status-value { font-weight: 700; }
    .dash-status-note { font-size: 0.875rem; display: flex; flex-direction: column; gap: var(--s-1); }

    /* Upload zone: a paper note taped to the panel. */
    .dash-note {
        position: relative;
        display: flex; flex-direction: column; align-items: center; gap: var(--s-3);
        padding: var(--s-6) var(--s-4) var(--s-5);
        background: var(--paper);
        border: 1.5px dashed var(--border-ctl);
        border-radius: var(--r);
        text-align: center;
        overflow-wrap: anywhere;
    }
    .dash-note > * { max-width: 100%; min-width: 0; }
    .dash-doodle { width: 46px; height: 46px; color: var(--red); }
    .dash-drop-title { font-weight: 700; }
    .dash-drop-hint { color: var(--muted-ink); font-size: 0.875rem; }
    .dash-drop-file { font-size: 0.875rem; overflow-wrap: anywhere; }
    .dash-progress { width: 100%; display: flex; flex-direction: column; gap: var(--s-2); text-align: left; }
    .dash-progress-row { display: flex; flex-wrap: wrap; align-items: center; justify-content: space-between; gap: var(--s-2); font-size: 0.875rem; }

    .dash-check {
        display: flex; align-items: flex-start; gap: var(--s-3);
        padding: var(--s-3); background: var(--paper);
        border: 1.5px dashed var(--border-soft); border-radius: var(--r);
    }
    .dash-check label { font-size: 0.875rem; line-height: 1.5; cursor: pointer; }
    .dash-check strong { display: block; font-weight: 700; margin-bottom: var(--s-1); }

    .dash-table { min-width: 640px; }
    .dash-source { max-width: 240px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; font-weight: 700; }
    .dash-stages {
        display: flex; flex-wrap: wrap; gap: var(--s-2);
        min-height: 44px; padding: 0; background: none; border: 0;
        font: inherit; color: inherit; text-align: left; cursor: pointer;
    }
    .dash-mark-failed { border-style: dashed; border-color: var(--muted-ink); color: var(--muted-ink); }
    .dash-actions { display: flex; align-items: center; justify-content: flex-end; gap: var(--s-2); flex-wrap: wrap; }
    .dash-reason { font-size: 0.75rem; max-width: 180px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; color: var(--muted-ink); }

    .dash-modal { position: fixed; inset: 0; z-index: var(--z-modal); display: flex; align-items: center; justify-content: center; padding: var(--s-4); }
    .dash-modal-scrim { position: absolute; inset: 0; background: var(--ink); opacity: .45; }
    .dash-modal-panel { position: relative; width: 100%; max-width: 640px; max-height: 85dvh; overflow-y: auto; background: var(--card); }
    .dash-modal-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--s-4); border-bottom: 1.5px solid var(--ink); padding-bottom: var(--s-3); margin-bottom: var(--s-4); }
    .dash-modal-source { font-size: 0.875rem; color: var(--muted-ink); overflow-wrap: anywhere; }
    .dash-stage-row { display: flex; align-items: baseline; justify-content: space-between; gap: var(--s-3); padding: var(--s-3) 0; border-bottom: 1px dashed var(--border-soft); }
    .dash-stage-error { margin-top: var(--s-2); padding: var(--s-2); border: 1.5px dashed var(--border-ctl); font-size: 0.75rem; overflow-wrap: anywhere; }

    @media (max-width: 760px) {
        .dash-table { min-width: 0; }
        .dash-table thead { display: none; }
        .dash-table tbody, .dash-table tr, .dash-table td { display: block; width: 100%; }
        .dash-table tr { padding: var(--s-4) 0; border-bottom: 1px dashed var(--border-soft); }
        .dash-table td { padding: 0; border-bottom: 0; }
        .dash-table td + td { margin-top: var(--s-2); }
        .dash-source { max-width: 100%; }
        .dash-actions { justify-content: flex-start; }
    }
</style>

    @php
        $checks = [
            ['label' => 'Whisper', 'on' => (bool) ($statuses['whisper'] ?? false), 'onText' => 'Online', 'offText' => 'Offline'],
            ['label' => 'Face Tracker', 'on' => (bool) ($statuses['face'] ?? false), 'onText' => 'Online', 'offText' => 'Offline'],
            ['label' => 'LLM (' . ucfirst($statuses['llm_driver'] ?? 'ollama') . ')', 'on' => (bool) ($statuses['llm'] ?? false), 'onText' => 'Online', 'offText' => 'Offline'],
            ['label' => 'Queue Worker', 'on' => (bool) ($statuses['queue'] ?? false), 'onText' => 'Berjalan', 'offText' => 'Berhenti'],
        ];
        $offline = array_values(array_filter($checks, fn ($c) => ! $c['on']));
    @endphp

    <div class="page-head rise" style="--i:0">
        <div class="row row-wrap between">
            <h1>Dashboard</h1>
            @if($poll)
                <span class="tag"><span class="spinner" aria-hidden="true"></span>Memproses queue</span>
            @endif
        </div>
        <p>Masukkan video, pantau pipeline, lalu review klip yang dihasilkan.</p>
        <p class="dash-paths">
            Setelah klip siap: atur jadwal di <a href="/calendar" wire:navigate>Kalender</a>,
            atur gaya bawaan di <a href="/brand" wire:navigate>Brand kit</a>,
            kelola musik &amp; gambar di <a href="/assets" wire:navigate>Aset</a>.
        </p>
    </div>

    {{-- Status decides whether submitting now will actually produce anything. --}}
    <section class="panel rise" aria-labelledby="dash-status-title" style="--i:1">
        <div class="section-head">
            <h2 id="dash-status-title">Status Sistem</h2>
            <span class="muted small">Cache 5–60 detik</span>
        </div>

        <ul class="dash-status-list">
            @foreach($checks as $check)
                <li class="dash-status-item">
                    <span class="mark {{ $check['on'] ? 'mark-on' : '' }}" aria-hidden="true"></span>
                    <span class="dash-status-name">{{ $check['label'] }}</span>
                    <span class="dash-status-value">{{ $check['on'] ? $check['onText'] : $check['offText'] }}</span>
                </li>
            @endforeach
        </ul>

        @if($offline)
            <div class="callout" style="margin-top: var(--s-5)">
                <div class="dash-status-note">
                    <span class="callout-title">Layanan belum siap: {{ implode(', ', array_map(fn ($c) => $c['label'], $offline)) }}</span>
                    <span>Video masih bisa diunggah, tetapi pipeline akan berhenti di tahap yang bergantung pada layanan tersebut sampai layanannya hidup.</span>
                </div>
            </div>
        @endif

        <div class="row row-wrap" style="margin-top: var(--s-5)">
            @if(!($statuses['queue'] ?? false))
                <button type="button" wire:click="wakeUpQueue" class="btn btn-sm" wire:loading.attr="disabled" wire:target="wakeUpQueue">
                    <span wire:loading.remove wire:target="wakeUpQueue">Bangunkan Antrean</span>
                    <span wire:loading wire:target="wakeUpQueue"><span class="spinner" aria-hidden="true"></span>Membangunkan…</span>
                </button>
            @endif
            <button type="button" wire:click="restartQueue" class="btn btn-sm btn-secondary" wire:loading.attr="disabled" wire:target="restartQueue">
                <span wire:loading.remove wire:target="restartQueue">Restart Queue (.env)</span>
                <span wire:loading wire:target="restartQueue"><span class="spinner" aria-hidden="true"></span>Merestart…</span>
            </button>
        </div>
    </section>

    <div id="upload-section" class="dash-ingest">

        <div class="panel rise" style="--i:2">
            <div class="section-head">
                <h2>Upload File Video</h2>
            </div>

            @if($uploadError)
                <p class="field-error" role="alert" style="margin-bottom: var(--s-4)">{{ $uploadError }}</p>
            @endif

            <form wire:submit="save" class="stack">
                <div class="dash-note tape"
                     x-data="{ isUploading: false, progress: 0 }"
                     x-on:livewire-upload-start="isUploading = true"
                     x-on:livewire-upload-finish="isUploading = false"
                     x-on:livewire-upload-error="isUploading = false"
                     x-on:livewire-upload-progress="progress = $event.detail.progress"
                     x-on:upload-cleared="document.getElementById('video-upload-input').value = ''">

                    <div x-show="!isUploading" class="stack" style="align-items: center; gap: var(--s-2)">
                        <svg class="doodle dash-doodle" viewBox="0 0 48 48" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                            <path d="M25 5 C 23 14, 26 22, 24 32" />
                            <path d="M16 25 C 19 28, 21 30, 24 35 C 27 30, 30 28, 33 25" />
                        </svg>
                        <span class="dash-drop-title">Pilih berkas video dari perangkat ini</span>
                        <span class="dash-drop-hint">MP4, MOV, MKV, WebM, AVI · maks. 3 jam</span>

                        <label for="video-upload-input" class="sr-only">Berkas video</label>
                        <input type="file" wire:model="upload" id="video-upload-input"
                               accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo"
                               class="sr-only">
                        <button type="button" class="btn btn-sm btn-secondary"
                                onclick="document.getElementById('video-upload-input').click()">
                            Pilih Berkas Video
                        </button>

                        @if($upload)
                            <span class="dash-drop-file">Berkas siap: {{ $upload->getClientOriginalName() }}</span>
                        @endif
                    </div>

                    <div x-show="isUploading" class="dash-progress" style="display: none">
                        <div class="dash-progress-row">
                            <span><span class="spinner" aria-hidden="true"></span> Sedang mengunggah ke server…</span>
                            <span class="mono" x-text="progress + '%'">0%</span>
                        </div>
                        <div class="meter" role="progressbar" aria-label="Progres unggahan" aria-valuemin="0" aria-valuemax="100" x-bind:aria-valuenow="progress">
                            <span x-bind:style="'width: ' + progress + '%'"></span>
                        </div>
                    </div>
                </div>

                @error('upload')
                    <span class="field-error">{{ $message }}</span>
                @enderror

                <div class="dash-check">
                    <input type="checkbox" wire:model="autoClip" id="auto-clip-upload">
                    <label for="auto-clip-upload">
                        <strong>Render &amp; Ekspor Otomatis (Auto-Clip)</strong>
                        Langsung memotong 9:16 untuk 3 klip terbaik (skor &ge; 75). Nonaktifkan jika hanya ingin mereview klip terlebih dahulu.
                    </label>
                </div>

                <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save,upload">
                    <span wire:loading.remove wire:target="save">Mulai Ingest &amp; Proses Video</span>
                    <span wire:loading wire:target="save"><span class="spinner" aria-hidden="true"></span>Memproses…</span>
                </button>
            </form>
        </div>

        <div class="panel rise" style="--i:3">
            <div class="section-head">
                <h2>Import Video dari URL</h2>
            </div>

            <form wire:submit="ingestUrl" class="stack">
                <div class="field">
                    <label for="url-input">Tautan URL (YouTube, TikTok, dll.)</label>
                    <input type="url" wire:model="url" id="url-input" placeholder="https://www.youtube.com/watch?v=...">
                    @if($urlError)
                        <span class="field-error" role="alert">{{ $urlError }}</span>
                    @endif
                </div>

                <div class="field">
                    <label for="resolution-select">Batas resolusi unduhan</label>
                    <select wire:model="resolution" id="resolution-select">
                        <option value="best">Kualitas terbaik (best MP4)</option>
                        <option value="1080p">Maksimal 1080p</option>
                        <option value="720p">Maksimal 720p</option>
                        <option value="480p">Maksimal 480p (hemat bandwidth)</option>
                        <option value="360p">Maksimal 360p (sangat cepat)</option>
                    </select>
                </div>

                <div class="dash-check">
                    <input type="checkbox" wire:model="autoClip" id="auto-clip-url">
                    <label for="auto-clip-url">
                        <strong>Render &amp; Ekspor Otomatis (Auto-Clip)</strong>
                        Langsung memotong 9:16 untuk 3 klip terbaik (skor &ge; 75).
                    </label>
                </div>

                <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="ingestUrl">
                    <span wire:loading.remove wire:target="ingestUrl">Unduh &amp; Proses Video</span>
                    <span wire:loading wire:target="ingestUrl"><span class="spinner" aria-hidden="true"></span>Menghubungi server…</span>
                </button>
            </form>
        </div>
    </div>

    <section class="panel rise" aria-labelledby="dash-videos-title" style="--i:4">
        <div class="section-head">
            <h2 id="dash-videos-title">Daftar Video Project</h2>
            <span class="mono muted">{{ $videos->count() }} entri</span>
        </div>

        @if($videos->isEmpty())
            <div class="empty">
                <svg class="doodle" viewBox="0 0 64 64" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                    <rect x="8" y="14" width="48" height="34" rx="3" />
                    <path d="M8 24 L 56 24" />
                    <path d="M17 24 L 15 14 M 30 24 L 29 14 M 44 24 L 43 14" />
                    <path d="M22 33 C 27 30, 33 36, 40 32" />
                    <path d="M40 32 L 35 29 M 40 32 L 35 35" />
                </svg>
                <p>Belum ada video project. Unggah berkas atau tempel URL video di atas; pipeline berjalan otomatis setelah baris video terbentuk.</p>
                <a href="#upload-section" class="btn btn-secondary">Ke area unggah</a>
            </div>
        @else
            <div class="table-wrap">
                <table class="dash-table">
                    <thead>
                        <tr>
                            <th scope="col">#</th>
                            <th scope="col">Detail Sumber</th>
                            <th scope="col">Durasi</th>
                            <th scope="col">Status Pipeline</th>
                            <th scope="col" class="num">Kandidat</th>
                            <th scope="col" class="num">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($videos as $video)
                            <tr wire:key="video-{{ $video->id }}">
                                <td class="mono muted">{{ $video->id }}</td>
                                <td>
                                    <div class="dash-source" title="{{ $video->source_ref ?? '—' }}">{{ $video->source_ref ?? '—' }}</div>
                                    <span class="muted small">{{ strtoupper($video->source_type) }}</span>
                                </td>
                                <td class="mono">
                                    @if($video->duration_seconds)
                                        {{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}
                                    @else — @endif
                                </td>
                                <td>
                                    <button type="button" class="dash-stages" wire:click="showStatusModal({{ $video->id }})"
                                            aria-label="Detail pipeline video {{ $video->id }}">
                                        @if($video->status === 'failed')
                                            <span class="tag tag-dashed">Pipeline gagal</span>
                                        @else
                                            @foreach($video->stageProgress() as $stage)
                                                @php
                                                    $markClass = match($stage['state']) {
                                                        'done' => 'mark mark-on',
                                                        'failed' => 'mark dash-mark-failed',
                                                        default => 'mark',
                                                    };
                                                    $stageText = match($stage['state']) {
                                                        'active' => $stage['label'] . ' · berjalan',
                                                        'failed' => $stage['label'] . ' · gagal',
                                                        default => $stage['label'],
                                                    };
                                                    $tagClass = match($stage['state']) {
                                                        'done' => 'tag',
                                                        'failed' => 'tag tag-dashed',
                                                        default => 'tag',
                                                    };
                                                @endphp
                                                <span class="{{ $tagClass }}" style="white-space: nowrap">
                                                    <span class="{{ $markClass }}" aria-hidden="true"></span>
                                                    {{ $stageText }}
                                                </span>
                                            @endforeach
                                        @endif
                                    </button>
                                </td>
                                <td class="num mono">
                                    @if($video->clip_candidates_count > 0)
                                        {{ $video->clip_candidates_count }}
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <div class="dash-actions">
                                        @if(in_array($video->status, ['reviewing','done']))
                                            <a href="/videos/{{ $video->id }}/review" wire:navigate class="btn btn-sm btn-secondary">Review</a>
                                        @elseif($video->status === 'failed')
                                            @php $reason = $video->failureReason(); @endphp
                                            <div class="stack" style="gap: 2px; align-items: flex-end">
                                                <span class="tag tag-solid">Gagal</span>
                                                @if($reason)
                                                    <span class="dash-reason" title="{{ $reason }}">{{ $reason }}</span>
                                                @endif
                                            </div>
                                        @else
                                            <span class="tag"><span class="spinner" aria-hidden="true"></span>Memproses</span>
                                        @endif

                                        <button type="button" wire:click="deleteVideo({{ $video->id }})"
                                                wire:confirm="Hapus video ini beserta kandidat & ekspor terkait secara permanen?"
                                                class="btn btn-sm btn-quiet"
                                                aria-label="Hapus video {{ $video->id }}">Hapus</button>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </section>

    <livewire:activity-feed />

    @if($showStatusModal && $selectedVideo)
        <div class="dash-modal">
            <div class="dash-modal-scrim" wire:click="closeStatusModal" aria-hidden="true"></div>

            <div class="dash-modal-panel panel" role="dialog" aria-modal="true" aria-labelledby="dash-modal-title"
                 wire:keydown.escape="closeStatusModal">
                <div class="dash-modal-head">
                    <div class="stack" style="gap: var(--s-1)">
                        <h2 id="dash-modal-title">Detail Pipeline #{{ $selectedVideo->id }}</h2>
                        <span class="dash-modal-source">Sumber: <span class="mono">{{ $selectedVideo->source_ref }}</span></span>
                    </div>
                    <button type="button" class="btn btn-sm btn-secondary" wire:click="closeStatusModal">Tutup</button>
                </div>

                <div>
                    @foreach($selectedVideo->stageProgress() as $progress)
                        @php
                            $dbJob = $selectedVideo->pipelineJobs->firstWhere('stage', $progress['key']);
                            $state = $progress['state'];
                            $stateLabel = match($state) {
                                'done' => 'Selesai',
                                'active' => 'Berjalan',
                                'failed' => 'Gagal',
                                default => 'Menunggu',
                            };
                            $markClass = match($state) {
                                'done' => 'mark mark-on',
                                'failed' => 'mark dash-mark-failed',
                                default => 'mark',
                            };
                        @endphp

                        <div class="dash-stage-row">
                            <strong class="row" style="gap: var(--s-2)">
                                <span class="{{ $markClass }}" aria-hidden="true"></span>
                                {{ $progress['label'] }}
                            </strong>
                            <span class="tag {{ $state === 'failed' ? 'tag-dashed' : '' }}">{{ $stateLabel }}</span>
                        </div>
                        @if($dbJob && $dbJob->last_error)
                            <div class="dash-stage-error mono">{{ $dbJob->last_error }}</div>
                        @endif
                    @endforeach
                </div>
            </div>
        </div>
    @endif
</div>
