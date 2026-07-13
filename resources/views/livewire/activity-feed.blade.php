<div @if($poll) wire:poll.4s @endif class="panel" style="content-visibility: auto;">
    <div class="row between" style="margin-bottom: 20px;">
        <strong style="font-family: 'Outfit', sans-serif; font-size: 16px; font-weight: 700; letter-spacing: -0.2px;">Aktivitas Pipeline Terbaru</strong>
        @if($poll)
            <span class="badge badge-blue" style="box-shadow: 0 0 12px rgba(59, 130, 246, 0.25);">
                <span class="spin"></span>&nbsp;Live Feed
            </span>
        @endif
    </div>

    @if($events->isEmpty())
        <div class="empty" style="padding:24px;">Belum ada riwayat aktivitas pipeline yang tercatat.</div>
    @else
        <!-- Vertical Timeline Layout -->
        <div style="position: relative; padding-left: 20px; border-left: 1px solid var(--border); display: flex; flex-direction: column; gap: 18px;">
            @foreach($events as $e)
                @php
                    [$cls,$label] = match($e->status) {
                        'done' => ['badge-green','Selesai'],
                        'running' => ['badge-blue','Berjalan'],
                        'queued' => ['badge-amber','Dalam Antrian'],
                        'failed' => ['badge-red','Gagal'],
                        default => ['badge-gray', $e->status],
                    };
                    $stageLabel = ucfirst($e->stage);
                    
                    // Node color matching status
                    $dotColor = match($e->status) {
                        'done' => 'var(--green)',
                        'running' => 'var(--blue)',
                        'queued' => 'var(--amber)',
                        'failed' => 'var(--red)',
                        default => 'var(--gray)',
                    };
                @endphp
                
                <!-- Timeline Item -->
                <div wire:key="pj-{{ $e->id }}" style="position: relative; display: flex; flex-direction: column; gap: 6px;">
                    <!-- Timeline Node Dot -->
                    <div style="position: absolute; left: -26px; top: 8px; width: 11px; height: 11px; border-radius: 50%; background: {{ $dotColor }}; border: 2px solid var(--bg); box-shadow: 0 0 10px {{ $dotColor }};"></div>
                    
                    <!-- Content Card -->
                    <div class="row between" style="padding: 12px 16px; border-radius: 12px; border: 1px solid var(--border); background: var(--panel-2); transition: border-color 0.2s ease, transform 0.2s ease;"
                         onmouseover="this.style.borderColor='var(--border-hover)'; this.style.transform='translateX(4px)';"
                         onmouseout="this.style.borderColor='var(--border)'; this.style.transform='translateX(0)';">
                        <div class="row" style="gap: 12px; min-width: 0; flex-wrap: wrap;">
                            <!-- Stage Badge -->
                            <span class="badge {{ $cls }}" style="font-size: 10px; padding: 3px 10px;">
                                @if(in_array($e->status,['running','queued']))
                                    <span class="spin" style="width:8px;height:8px;border-width:1.5px;margin-right:2px;"></span>
                                @endif
                                {{ $stageLabel }} &middot; {{ $label }}
                            </span>
                            
                            <!-- Video Ref Link -->
                            <span class="muted" style="font-size: 12px; font-weight: 600; white-space: nowrap;">
                                Video #{{ $e->video_id }}
                            </span>
                            
                            <span class="muted" style="font-size: 12px; max-width: 280px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;">
                                {{ $e->video?->source_ref }}
                            </span>
                        </div>
                        
                        <!-- Diff time -->
                        <span class="muted" style="font-size: 11px; white-space: nowrap;">
                            {{ $e->updated_at?->diffForHumans() }}
                        </span>
                    </div>

                    <!-- Error details banner if failed -->
                    @if($e->status==='failed' && $e->last_error)
                        <div style="margin-left: 8px; margin-top: -2px; border: 1px solid rgba(239, 68, 68, 0.15); border-radius: 8px; padding: 10px 14px; background: rgba(239, 68, 68, 0.03); color: #f87171; font-size: 12px; display: flex; gap: 8px; align-items: flex-start;">
                            <svg xmlns="http://www.w3.org/2000/svg" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="flex-shrink: 0; margin-top: 2px;">
                                <circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/>
                            </svg>
                            <p style="margin: 0; line-height: 1.5; font-weight: 500;">
                                <strong>Error Log:</strong> {{ $e->last_error }}
                            </p>
                        </div>
                    @endif
                </div>
            @endforeach
        </div>
    @endif
</div>

