<div @if($poll) wire:poll.4s @endif class="panel">
    <div class="row between" style="margin-bottom:12px;">
        <strong>Aktivitas terbaru</strong>
        @if($poll)
            <span class="badge badge-blue"><span class="spin"></span>&nbsp;Live</span>
        @endif
    </div>

    @if($events->isEmpty())
        <div class="empty" style="padding:24px;">Belum ada aktivitas pipeline.</div>
    @else
        <div class="grid" style="gap:8px;">
            @foreach($events as $e)
                @php
                    [$cls,$label] = match($e->status) {
                        'done' => ['badge-green','selesai'],
                        'running' => ['badge-blue','berjalan'],
                        'queued' => ['badge-amber','antri'],
                        'failed' => ['badge-red','gagal'],
                        default => ['badge-gray', $e->status],
                    };
                    $stageLabel = ucfirst($e->stage);
                @endphp
                <div class="row between" wire:key="pj-{{ $e->id }}"
                     style="padding:8px 10px; border:1px solid var(--border); border-radius:8px; background:var(--panel-2);">
                    <div class="row" style="gap:10px; align-items:baseline; min-width:0;">
                        <span class="badge {{ $cls }}" style="font-size:11px;">
                            @if(in_array($e->status,['running','queued']))<span class="spin" style="width:9px;height:9px;"></span>@endif
                            {{ $stageLabel }} · {{ $label }}
                        </span>
                        <span class="muted" style="font-size:12px; white-space:nowrap;">
                            video #{{ $e->video_id }}
                        </span>
                        <span class="muted" style="font-size:12px; overflow:hidden; text-overflow:ellipsis; white-space:nowrap;">
                            {{ $e->video?->source_ref }}
                        </span>
                        @if($e->status==='failed' && $e->last_error)
                            <span style="font-size:12px; color:var(--red); overflow:hidden; text-overflow:ellipsis;
                                   white-space:nowrap; max-width:280px;" title="{{ $e->last_error }}">
                                — {{ $e->last_error }}
                            </span>
                        @endif
                    </div>
                    <span class="muted" style="font-size:11px; white-space:nowrap;">
                        {{ $e->updated_at?->diffForHumans() }}
                    </span>
                </div>
            @endforeach
        </div>
    @endif
</div>
