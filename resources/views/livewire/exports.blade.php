<div @if($poll) wire:poll.4s @endif>
    <div class="row between" style="margin-bottom: 8px;">
        <div>
            <h1 class="page-title">Exports</h1>
            <p class="page-sub" style="margin-bottom:0;">Klip 9:16 final. Unduh yang sudah selesai di-render.</p>
        </div>
        @if($poll)
            <span class="badge badge-blue"><span class="spin"></span>&nbsp;Render berjalan&hellip;</span>
        @endif
    </div>

    <div class="panel" style="margin-top: 18px;">
        @if($exports->isEmpty())
            <div class="empty">
                Belum ada export. Setujui kandidat klip di halaman
                <a href="/">Dashboard &rarr; Review</a> untuk memulai render.
            </div>
        @else
            <table>
                <thead>
                    <tr>
                        <th style="width:52px;">#</th>
                        <th>Sumber</th>
                        <th style="width:90px;">Rasio</th>
                        <th style="width:110px;">Watermark</th>
                        <th style="width:150px;">Status</th>
                        <th style="width:130px;"></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($exports as $e)
                        @php
                            $video = $e->clipCandidate?->video;
                            [$cls,$label] = match($e->status) {
                                'rendered' => ['badge-green','Selesai'],
                                'rendering' => ['badge-blue','Rendering'],
                                'queued' => ['badge-amber','Antri'],
                                'failed' => ['badge-red','Gagal'],
                                default => ['badge-gray', $e->status],
                            };
                        @endphp
                        <tr wire:key="export-{{ $e->id }}">
                            <td class="muted">{{ $e->id }}</td>
                            <td>
                                <div style="max-width:240px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                                    {{ $video?->source_ref ?? '—' }}
                                </div>
                                <span class="muted" style="font-size:12px;">
                                    @if($video) video #{{ $video->id }} @endif
                                    @if($e->clipCandidate) &middot; klip #{{ $e->clipCandidate->id }} @endif
                                </span>
                            </td>
                            <td class="muted">{{ $e->aspect_ratio }}</td>
                            <td>
                                @if($e->watermark_applied)
                                    <span class="badge badge-green">Ya</span>
                                @else
                                    <span class="muted">—</span>
                                @endif
                            </td>
                            <td>
                                <span class="badge {{ $cls }}">
                                    @if($e->status==='rendering' || $e->status==='queued')<span class="spin" style="width:9px;height:9px;"></span>@endif
                                    {{ $label }}
                                </span>
                                @if($e->status==='failed' && $e->last_error)
                                    <div class="muted" style="font-size:11px; margin-top:4px; max-width:150px;
                                         overflow:hidden; text-overflow:ellipsis; white-space:nowrap;"
                                         title="{{ $e->last_error }}">
                                        {{ $e->last_error }}
                                    </div>
                                @endif
                            </td>
                            <td>
                                @if($e->status==='rendered' && $e->output_path)
                                    <a href="/api/exports/{{ $e->id }}/download" class="btn btn-sm btn-primary">Download</a>
                                @elseif($e->status==='failed')
                                    <span class="muted" style="font-size:13px;">—</span>
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
</div>
