<div @if($poll) wire:poll.4s @endif class="grid" style="gap: 24px;">
    <!-- Page Header -->
    <div class="row between" style="align-items: flex-start;">
        <div>
            <h1 class="page-title">Daftar Klip Ekspor</h1>
            <p class="page-sub" style="margin-bottom:0;">Unduh hasil potongan klip vertikal (9:16) berkualitas tinggi yang telah berhasil di-render.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue" style="box-shadow: 0 0 12px rgba(59, 130, 246, 0.25);">
                <span class="spin"></span>&nbsp;Proses Rendering Aktif
            </span>
        @endif
    </div>

    <!-- Exports Table Panel -->
    <div class="panel" style="content-visibility: auto;">
        @if($exports->isEmpty())
            <div class="empty">
                Belum ada klip yang diekspor. Pergi ke halaman 
                <a href="/">Dashboard &rarr; Review Hasil</a> untuk menyetujui kandidat klip dan memulai render.
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
                                    <div style="max-width:260px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap; font-weight: 600; color: var(--text);">
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
                                        @if($e->status==='rendering' || $e->status==='queued')<span class="spin" style="width:8px;height:8px;border-width:1.5px;margin-right:2px;"></span>@endif
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
                                        <a href="/api/exports/{{ $e->id }}/download" class="btn btn-sm btn-primary" style="padding: 5px 14px;">Download MP4</a>
                                    @elseif($e->status==='failed')
                                        <span class="muted">—</span>
                                    @else
                                        <span class="muted" style="font-size:12px; display: inline-flex; align-items: center; gap: 6px;">
                                            <span class="spin" style="width:10px;height:10px;border-width:1.5px;"></span> Rendering&hellip;
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

