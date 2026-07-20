<div @if($poll) wire:poll.4s @endif class="panel">
    <div class="row between" style="margin-bottom: 20px;">
        <h3 style="margin: 0; font-family: var(--font-title); font-size: 20px; font-weight: 800; color: var(--text-title);">
            Aktivitas Pipeline Terbaru
        </h3>
        @if($poll)
            <span class="badge badge-purple">
                <i class="ph ph-spinner-gap spin-rotate"></i>&nbsp;Live Feed
            </span>
        @endif
    </div>

    @if($events->isEmpty())
        <div style="text-align: center; padding: 30px; color: var(--text-muted); font-size: 13px;">
            Belum ada riwayat aktivitas pipeline yang tercatat.
        </div>
    @else
        <!-- Timeline Layout -->
        <div style="position: relative; padding-left: 20px; border-left: 2px dashed var(--border-color); display: flex; flex-direction: column; gap: 16px;">
            @foreach($events as $e)
                @php
                    [$badgeCls, $label] = match($e->status) {
                        'done' => ['badge-green', 'Selesai'],
                        'running' => ['badge-purple', 'Berjalan'],
                        'queued' => ['badge-amber', 'Dalam Antrian'],
                        'failed' => ['badge-red', 'Gagal'],
                        default => ['badge-purple', $e->status],
                    };
                    $dotColor = match($e->status) {
                        'done' => '#10b981',
                        'running' => 'var(--purple-primary)',
                        'queued' => '#f59e0b',
                        'failed' => '#ef4444',
                        default => 'var(--purple-primary)',
                    };
                @endphp
                
                <div wire:key="pj-{{ $e->id }}" style="position: relative;">
                    <!-- Timeline Node -->
                    <div style="position: absolute; left: -27px; top: 12px; width: 12px; height: 12px; border-radius: 50%; background: {{ $dotColor }}; border: 2px solid var(--bg-surface); box-shadow: 0 0 10px {{ $dotColor }};"></div>
                    
                    <!-- Content Card -->
                    <div class="row between" style="padding: 12px 18px; border-radius: 14px; border: 1px solid var(--border-color); background: var(--bg-surface-subtle); transition: all 0.2s ease;">
                        <div class="row" style="gap: 12px; flex-wrap: wrap;">
                            <span class="badge {{ $badgeCls }}" style="font-size: 10px;">
                                @if(in_array($e->status,['running','queued']))
                                    <i class="ph ph-spinner-gap spin-rotate"></i>
                                @endif
                                {{ ucfirst($e->stage) }} &middot; {{ $label }}
                            </span>

                            
                            <span style="font-size: 12.5px; font-weight: 700; color: var(--text-title);">
                                Video #{{ $e->video_id }}
                            </span>
                            
                            <span style="font-size: 12px; color: var(--text-muted); max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $e->video?->source_ref }}
                            </span>
                        </div>
                        
                        <span style="font-size: 11px; color: var(--text-muted); font-weight: 500;">
                            {{ $e->updated_at?->diffForHumans() }}
                        </span>
                    </div>

                    @if($e->status==='failed' && $e->last_error)
                        <div style="margin-top: 6px; border: 1px solid rgba(239, 68, 68, 0.2); border-radius: 10px; padding: 10px 14px; background: rgba(239, 68, 68, 0.08); color: #ef4444; font-size: 12px;">
                            <strong>Log Error:</strong> {{ $e->last_error }}
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>
