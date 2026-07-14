<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Breadcrumb Navigation -->
    <nav class="breadcrumb" style="font: 11px/1 var(--mono); text-transform: uppercase; letter-spacing: 0.15em; margin-bottom: 16px; display: flex; align-items: center; gap: 6px;">
        <a href="/" wire:navigate style="color: var(--muted); display: inline-flex; align-items: center; gap: 4px;"><i class="ph ph-house" style="font-size: 13px;"></i> Dashboard</a>
        <span style="color: var(--line);">/</span>
        <span style="color: var(--accent); font-weight: 600;">Exports</span>
    </nav>

    <!-- Page Header -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Daftar Klip Ekspor</h1>
            <p class="page-sub" style="margin-bottom:0;">Unduh hasil potongan klip vertikal (9:16) berkualitas tinggi yang telah berhasil di-render.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue" style="background: var(--tile-5); color: var(--ink); border: 1px solid rgba(0,0,0,0.06);">
                <i class="ph ph-spinner-gap spin-rotate" style="font-size:14px;"></i>&nbsp;Proses Rendering Aktif
            </span>
        @endif
    </div>

    <!-- Exports Table Panel -->
    <div class="panel" style="content-visibility: auto;">
        @if($exports->isEmpty())
            <div class="empty">
                Belum ada klip yang diekspor. Pergi ke halaman 
                <a href="/" wire:navigate>Dashboard &rarr; Review Hasil</a> untuk menyetujui kandidat klip dan memulai render.
            </div>
        @else
            <div style="overflow-x: auto;">
                <table>
                    <thead>
                        <tr>
                            <th style="width:52px; text-align: center;">#</th>
                            <th>Detail Video Sumber</th>
                            <th style="width:100px; text-align: center;">Rasio Aspek</th>
                            <th style="width:120px; text-align: center;">Watermark</th>
                            <th style="width:160px;">Status Ekspor</th>
                            <th style="width:130px; text-align: right;">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exports as $e)
                            @php
                                $video = $e->clipCandidate?->video;
                                [$cls,$label] = match($e->status) {
                                    'rendered' => ['badge-green','Selesai'],
                                    'rendering' => ['badge-blue','Rendering'],
                                    'queued' => ['badge-amber','Dalam Antrian'],
                                    'failed' => ['badge-red','Gagal'],
                                    default => ['badge-gray', $e->status],
                                };
                            @endphp
                            <tr wire:key="export-{{ $e->id }}">
                                <td style="text-align: center;" class="muted font-semibold">{{ $e->id }}</td>
                                <td>
                                    <div style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight: 700; color: var(--ink);">
                                        {{ $video?->source_ref ?? '—' }}
                                    </div>
                                    <span class="muted" style="font-size:11px; display: inline-flex; align-items: center; gap: 4px; margin-top: 2px;">
                                        Video #{{ $video?->id ?? '—' }} &middot; Klip Kandidat #{{ $e->clip_candidate_id }}
                                    </span>
                                </td>
                                <td style="text-align: center;" class="muted font-semibold">{{ $e->aspect_ratio }}</td>
                                <td style="text-align: center;">
                                    @if($e->watermark_applied)
                                        <span class="badge badge-green" style="font-size: 10px; padding: 2px 8px;">Aktif</span>
                                    @else
                                        <span class="muted">—</span>
                                    @endif
                                </td>
                                <td>
                                    <span class="badge {{ $cls }}">
                                        @if($e->status==='rendering' || $e->status==='queued')<i class="ph ph-spinner-gap spin-rotate" style="font-size:10px; margin-right:2px;"></i>@endif
                                        {{ $label }}
                                    </span>
                                    @if($e->status==='failed' && $e->last_error)
                                        <div class="muted" style="font-size:10px; margin-top:4px; max-width:160px;
                                             overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                             title="{{ $e->last_error }}">
                                            {{ $e->last_error }}
                                        </div>
                                    @endif
                                </td>
                                <td style="text-align: right;">
                                    @if($e->status==='rendered' && $e->output_path)
                                        <div style="display: flex; gap: 8px; justify-content: flex-end; align-items: center;">
                                            <a href="/openreel/?video_url={{ urlencode('/api/exports/' . $e->id . '/download') }}" target="_blank" class="btn btn-sm btn-outline" style="padding: 5px 10px; border-color: #3b82f6; color: #3b82f6; display: inline-flex; align-items: center; gap: 4px; border-radius: 8px; text-decoration: none;" title="Edit hasil render ini di OpenReel">
                                                <i class="ph ph-video" style="font-size:13px; vertical-align: middle;"></i> Edit di OpenReel
                                            </a>
                                            <a href="/api/exports/{{ $e->id }}/download" class="btn btn-sm btn-primary" style="padding: 5px 14px; border-color: var(--ink); background: var(--ink); color: var(--paper);">
                                                <i class="ph ph-download-simple" style="font-size:13px; vertical-align: middle;"></i> Download MP4
                                            </a>
                                        </div>
                                    @elseif($e->status==='failed')
                                        <span class="muted">—</span>
                                    @else
                                        <span class="muted" style="font-size:12px; display: inline-flex; align-items: center; gap: 6px;">
                                            <i class="ph ph-spinner-gap spin-rotate" style="font-size: 12px;"></i> Rendering&hellip;
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
