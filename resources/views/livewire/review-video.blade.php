<div @if($poll) wire:poll.4s @endif>
    <div class="row between" style="margin-bottom: 8px;">
        <div>
            <a href="/" class="muted" style="font-size:13px;">&larr; Dashboard</a>
            <h1 class="page-title" style="margin-top:4px;">Review — Video #{{ $video->id }}</h1>
            <p class="page-sub" style="margin-bottom:0;">
                {{ $video->source_ref ?? '—' }}
                @if($video->duration_seconds)
                    &middot; {{ gmdate($video->duration_seconds >= 3600 ? 'H:i:s' : 'i:s', $video->duration_seconds) }}
                @endif
                &middot; {{ $candidates->count() }} kandidat
            </p>
        </div>
        @if($poll)
            <span class="badge badge-blue"><span class="spin"></span>&nbsp;Render berjalan&hellip;</span>
        @endif
    </div>

    @if($flash)<div class="flash">{{ $flash }}</div>@endif
    @if($error)<div class="flash flash-error">{{ $error }}</div>@endif

    <div class="grid" style="grid-template-columns: 380px 1fr; align-items:start;">
        {{-- Player --}}
        <div class="panel" style="position:sticky; top:78px;">
            <video controls preload="metadata"
                   style="width:100%; border-radius:8px; background:#000; aspect-ratio:16/9;"
                   src="/videos/{{ $video->id }}/source">
                Browser tidak mendukung pemutar video.
            </video>
            <p class="muted" style="font-size:12px; margin:10px 0 0;">
                Preview sumber. Klip 9:16 final tersedia di halaman
                <a href="/exports">Exports</a> setelah render.
            </p>
        </div>

        {{-- Candidates --}}
        <div class="grid" style="gap:12px;">
            {{-- CTA overlay (campaign requirement: every clip needs on-screen CTA) --}}
            <div class="panel">
                <label class="muted" style="font-weight:600; display:block; margin-bottom:6px;">
                    On-screen CTA (di-burn ke klip yang di-approve)
                </label>
                <input type="text" wire:model="ctaText" maxlength="120"
                       placeholder="mis. IT'S OUT. IT'S ACTUALLY OUT."
                       style="width:100%; padding:9px 12px; border-radius:8px; background:var(--panel-2);
                              border:1px solid var(--border); color:var(--text); font-family:inherit; font-size:14px;">
                <div class="row" style="gap:6px; flex-wrap:wrap; margin-top:10px;">
                    @foreach($this->ctaPresets() as $preset)
                        <button type="button" class="btn btn-sm"
                                wire:click="$set('ctaText', @js($preset))"
                                style="font-weight:500;">{{ $preset }}</button>
                    @endforeach
                </div>
                @if(trim($ctaText) === '')
                    <p class="muted" style="font-size:12px; margin:8px 0 0; color:var(--amber);">
                        Kosong = klip tanpa CTA. Campaign mewajibkan CTA di setiap klip.
                    </p>
                @endif
            </div>

            @forelse($candidates as $c)
                @php
                    $dur = ($c->end_ms - $c->start_ms) / 1000;
                    [$sCls,$sLabel] = match($c->status) {
                        'approved' => ['badge-amber','Disetujui — render'],
                        'exported' => ['badge-green','Diekspor'],
                        'rejected' => ['badge-gray','Ditolak'],
                        default => ['badge-blue','Menunggu review'],
                    };
                    $fmt = fn($ms) => gmdate('i:s', intdiv($ms,1000));
                @endphp
                <div class="panel" wire:key="cand-{{ $c->id }}"
                     style="border-left:3px solid {{ $c->hook_score >= 75 ? 'var(--green)' : ($c->hook_score >= 50 ? 'var(--amber)' : 'var(--gray)') }};">
                    <div class="row between" style="align-items:flex-start;">
                        <div class="row" style="gap:14px; align-items:center;">
                            <div style="text-align:center; min-width:52px;">
                                <div style="font-size:26px; font-weight:800; line-height:1;
                                     color:{{ $c->hook_score >= 75 ? 'var(--green)' : ($c->hook_score >= 50 ? 'var(--amber)' : 'var(--muted)') }};">
                                    {{ $c->hook_score }}
                                </div>
                                <div class="muted" style="font-size:10px; text-transform:uppercase; letter-spacing:.5px;">score</div>
                            </div>
                            <div>
                                <div style="font-weight:600;">
                                    {{ $fmt($c->start_ms) }} &rarr; {{ $fmt($c->end_ms) }}
                                    <span class="muted" style="font-weight:400;">({{ number_format($dur,1) }}s)</span>
                                </div>
                                <span class="badge {{ $sCls }}" style="margin-top:4px;">{{ $sLabel }}</span>
                            </div>
                        </div>

                        <div class="row" style="gap:8px;">
                            @if(in_array($c->status, ['pending','rejected']))
                                <button class="btn btn-sm btn-green"
                                        wire:click="approve({{ $c->id }})"
                                        wire:loading.attr="disabled" wire:target="approve({{ $c->id }})">
                                    Approve
                                </button>
                            @endif
                            @if(in_array($c->status, ['pending','approved']))
                                <button class="btn btn-sm btn-red"
                                        wire:click="reject({{ $c->id }})"
                                        wire:loading.attr="disabled" wire:target="reject({{ $c->id }})">
                                    Reject
                                </button>
                            @endif
                        </div>
                    </div>

                    @if($c->score_rationale)
                        <p class="muted" style="margin:12px 0 0; font-size:13px; border-top:1px solid var(--border); padding-top:10px;">
                            {{ $c->score_rationale }}
                        </p>
                    @endif
                </div>
            @empty
                <div class="panel empty">
                    Belum ada kandidat klip. Kalau pipeline masih berjalan,
                    kandidat akan muncul otomatis di sini.
                </div>
            @endforelse
        </div>
    </div>
</div>
