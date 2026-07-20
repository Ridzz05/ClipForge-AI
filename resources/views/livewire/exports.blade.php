<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Breadcrumb -->
    <div style="font-size: 12px; font-weight: 600; color: var(--text-muted); display: flex; align-items: center; gap: 8px;">
        <a href="/" wire:navigate style="color: var(--text-muted);"><i class="ph ph-house"></i> Dashboard</a>
        <span>/</span>
        <span style="color: var(--purple-primary);">Exports & Klip Vertikal</span>
    </div>

    <!-- Header -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Daftar Klip Ekspor (9:16)</h1>
            <p class="page-sub" style="margin-bottom:0;">Unduh hasil potongan klip vertikal berkualifikasi tinggi yang telah selesai di-render oleh pipeline.</p>
        </div>
        @if($poll)
            <span class="badge badge-purple" style="padding: 8px 16px;">
                <i class="ph ph-spinner-gap spin-rotate"></i>&nbsp;Proses Rendering Aktif
            </span>
        @endif
    </div>

    <!-- Table Panel -->
    <div class="panel">
        @if($exports->isEmpty())
            <div style="text-align: center; padding: 60px 20px; color: var(--text-muted); font-size: 14px;">
                <i class="ph ph-film-strip" style="font-size: 42px; color: var(--purple-primary); margin-bottom: 12px; display: block;"></i>
                Belum ada klip yang diekspor. Pergi ke 
                <a href="/" wire:navigate style="font-weight: 700; color: var(--purple-primary);">Dashboard &rarr; Review Video</a> untuk menyetujui kandidat klip.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:50px; text-align: center;">#</th>
                            <th>Detail Video Sumber</th>
                            <th style="width:100px; text-align: center;">Rasio</th>
                            <th style="width:120px; text-align: center;">Watermark</th>
                            <th style="width:160px;">Status Ekspor</th>
                            <th style="width:240px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exports as $e)
                            @php
                                $video = $e->clipCandidate?->video;
                                $badgeCls = match($e->status) {
                                    'rendered' => 'badge-green',
                                    'rendering' => 'badge-purple',
                                    'queued' => 'badge-amber',
                                    'failed' => 'badge-red',
                                    default => 'badge-purple',
                                };
                                $label = match($e->status) {
                                    'rendered' => 'Selesai',
                                    'rendering' => 'Rendering',
                                    'queued' => 'Antrian',
                                    'failed' => 'Gagal',
                                    default => $e->status,
                                };
                            @endphp
                            <tr wire:key="export-{{ $e->id }}">
                                <td style="text-align: center; font-weight: 700; color: var(--text-muted);">{{ $e->id }}</td>
                                <td>
                                    <div style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight: 700; color: var(--text-title);">
                                        {{ $video?->source_ref ?? '—' }}
                                    </div>
                                    <span style="font-size:11px; color: var(--text-muted); display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        Video #{{ $video?->id ?? '—' }} &middot; Klip Kandidat #{{ $e->clip_candidate_id }}
                                    </span>
                                </td>
                                <td style="text-align: center; font-weight: 600;">{{ $e->aspect_ratio }}</td>
                                <td style="text-align: center;">
                                    @if($e->watermark_applied)
                                        <span class="badge badge-green">Aktif</span>
                                    @else
                                        <span style="color: var(--text-muted);">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $badgeCls }}">
                                        @if($e->status==='rendering' || $e->status==='queued')<i class="ph ph-spinner-gap spin-rotate"></i>@endif
                                        {{ $label }}
                                    </span>
                                    @if($e->status==='failed' && $e->last_error)
                                        <div style="font-size:10px; color: #ef4444; margin-top:4px; max-width:160px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;" title="{{ $e->last_error }}">
                                            {{ $e->last_error }}
                                        </div>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    @if($e->status==='rendered' && $e->output_path)
                                        <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                            <a href="/openreel/?video_url={{ urlencode('/api/exports/' . $e->id . '/download') }}" target="_blank" class="btn btn-sm btn-outline" style="border-color: #3b82f6; color: #3b82f6; font-weight: 700;">
                                                <i class="ph ph-video"></i> Edit di OpenReel
                                            </a>
                                            <a href="/api/exports/{{ $e->id }}/download" class="btn btn-sm">
                                                <i class="ph ph-download-simple"></i> Download MP4
                                            </a>
                                        </div>
                                    @elseif($e->status==='failed')
                                        <span style="color: var(--text-muted);">—</span>
                                    @else
                                        <span style="font-size:12px; color: var(--purple-primary); font-weight: 700; display: inline-flex; align-items: center; gap: 4px;">
                                            <i class="ph ph-spinner-gap spin-rotate"></i> Rendering...
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
</div>
