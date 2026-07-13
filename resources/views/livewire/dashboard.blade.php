<div @if($poll) wire:poll.4s @endif>
    <div class="row between" style="margin-bottom: 8px;">
        <div>
            <h1 class="page-title">Dashboard</h1>
            <p class="page-sub" style="margin-bottom:0;">Upload video panjang &rarr; pipeline otomatis memotong jadi klip pendek.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue"><span class="spin"></span>&nbsp;Memproses&hellip;</span>
        @endif
    </div>

    {{-- Upload panel --}}
    <div class="panel" style="margin-top: 18px;">
        @if($flash)
            <div class="flash">{{ $flash }}</div>
        @endif
        @if($uploadError)
            <div class="flash flash-error">{{ $uploadError }}</div>
        @endif

        <form wire:submit="save" class="grid" style="gap:14px;">
            <label class="muted" style="font-weight:600;">Pilih file video</label>

            <input type="file" wire:model="upload"
                   accept="video/mp4,video/quicktime,video/x-matroska,video/webm,video/x-msvideo">

            @error('upload') <span class="flash flash-error">{{ $message }}</span> @enderror

            <div class="row" style="gap:12px;">
                <button type="submit" class="btn btn-primary"
                        wire:loading.attr="disabled" wire:target="save,upload">
                    <span wire:loading.remove wire:target="save">Upload &amp; Proses</span>
                    <span wire:loading wire:target="save"><span class="spin"></span>&nbsp;Mengunggah&hellip;</span>
                </button>
                <span class="muted" wire:loading wire:target="upload">Menyiapkan file&hellip;</span>
                <span class="muted" style="font-size:13px;">Max 3 jam &middot; MP4/MOV/MKV/WebM/AVI</span>
            </div>
        </form>

        <div style="border-top:1px solid var(--border); margin:18px 0; text-align:center;">
            <span class="muted" style="font-size:12px; background:var(--panel); padding:0 10px; position:relative; top:-10px;">ATAU</span>
        </div>

        <form wire:submit="ingestUrl" class="grid" style="gap:14px; margin-top:-8px;">
            <label class="muted" style="font-weight:600;">Tempel URL video (YouTube, dll)</label>
            <div class="row" style="gap:10px;">
                <input type="url" wire:model="url" placeholder="https://www.youtube.com/watch?v=..."
                       style="flex:1; padding:9px 12px; border-radius:8px; background:var(--panel-2);
                              border:1px solid var(--border); color:var(--text); font-family:inherit; font-size:14px;">
                <button type="submit" class="btn btn-primary"
                        wire:loading.attr="disabled" wire:target="ingestUrl">
                    <span wire:loading.remove wire:target="ingestUrl">Ambil dari URL</span>
                    <span wire:loading wire:target="ingestUrl"><span class="spin"></span>&nbsp;Memproses&hellip;</span>
                </button>
            </div>
            @if($urlError)
                <span class="flash flash-error">{{ $urlError }}</span>
            @endif
            <span class="muted" style="font-size:13px;">Diunduh di background via yt-dlp. Hanya http/https publik.</span>
        </form>
    </div>

    {{-- Video list --}}
    <div class="panel" style="margin-top: 18px;">
        <div class="row between" style="margin-bottom: 10px;">
            <strong>Video ({{ $videos->count() }})</strong>
        </div>

        @if($videos->isEmpty())
            <div class="empty">Belum ada video. Upload di atas untuk memulai.</div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:52px;">#</th>
                        <th>Sumber</th>
                        <th style="width:90px;">Durasi</th>
                        <th style="width:320px;">Progress</th>
                        <th style="width:90px;">Klip</th>
                        <th style="width:120px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($videos as $video)
                        <tr wire:key="video-{{ $video->id }}">
                            <td class="muted">{{ $video->id }}</td>
                            <td>
                                <div style="max-width:220px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $video->source_ref ?? '—' }}
                                </div>
                                <span class="muted" style="font-size:12px;">{{ $video->source_type }}</span>
                            </td>
                            <td class="muted">
                                @if($video->duration_seconds)
                                    {{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}
                                @else — @endif
                            </td>
                            <td>
                                <div class="row" style="gap:4px;">
                                    @foreach($video->stageProgress() as $stage)
                                        @php
                                            $cls = match($stage['state']) {
                                                'done' => 'badge-green',
                                                'active' => 'badge-blue',
                                                'failed' => 'badge-red',
                                                default => 'badge-gray',
                                            };
                                        @endphp
                                        <span class="badge {{ $cls }}" style="font-size:11px; padding:2px 8px;"
                                              title="{{ $stage['label'] }}: {{ $stage['state'] }}">
                                            @if($stage['state']==='active')<span class="spin" style="width:9px;height:9px;"></span>@endif
                                            {{ $stage['label'] }}
                                        </span>
                                    @endforeach
                                </div>
                            </td>
                            <td>
                                @if($video->clip_candidates_count > 0)
                                    <span class="badge badge-blue">{{ $video->clip_candidates_count }}</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                @if(in_array($video->status, ['reviewing','done']))
                                    <a href="/videos/{{ $video->id }}/review" class="btn btn-sm btn-primary">Review</a>
                                @elseif($video->status === 'failed')
                                    @php $reason = $video->failureReason(); @endphp
                                    <span class="badge badge-red" @if($reason) title="{{ $reason }}" @endif>Gagal</span>
                                    @if($reason)
                                        <div class="muted" style="font-size:11px; margin-top:4px; max-width:180px;
                                             overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                             title="{{ $reason }}">
                                            {{ $reason }}
                                        </div>
                                    @endif
                                @else
                                    <span class="muted" style="font-size:13px;">menunggu&hellip;</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        @endif
    </div>

    {{-- Live pipeline activity timeline (P4) --}}
    <div style="margin-top:18px;">
        <livewire:activity-feed />
    </div>
</div>
