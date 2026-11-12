<div @if($poll) wire:poll.4s @endif class="panel">
<style>
    .dash-feed-list { list-style: none; margin: 0; padding: 0; }
    .dash-feed-item { padding: var(--s-3) 0; border-bottom: 1px dashed var(--border-soft); min-width: 0; }
    .dash-feed-item:last-child { border-bottom: 0; }
    .dash-feed-meta { display: flex; flex-wrap: wrap; align-items: baseline; gap: var(--s-2) var(--s-3); min-width: 0; }
    .dash-feed-video { font-weight: 700; font-size: 0.875rem; }
    .dash-feed-ref { font-size: 0.875rem; color: var(--muted-ink); max-width: 34ch; }
    .dash-feed-error {
        margin-top: var(--s-2); padding: var(--s-2); background: var(--hl);
        border: 1.5px dashed var(--border-ctl); border-radius: var(--r);
        font-size: 0.75rem; overflow-wrap: anywhere;
    }
    .dash-feed-live { display: inline-flex; align-items: center; gap: var(--s-2); font-size: 0.875rem; color: var(--muted-ink); }
    .dash-mark-failed { border-style: dashed; border-color: var(--muted-ink); color: var(--muted-ink); }
</style>
    <div class="section-head">
        <h2>Aktivitas Pipeline Terbaru</h2>
        @if($poll)
            <span class="dash-feed-live">
                <span class="spinner" aria-hidden="true"></span>Live feed
            </span>
        @endif
    </div>

    @if($events->isEmpty())
        <div class="empty">
            <svg class="doodle" viewBox="0 0 64 64" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M16 10 L 48 10 L 48 54 L 16 54 Z" />
                <path d="M24 22 L 40 22 M 24 32 L 40 32 M 24 42 L 34 42" />
                <path d="M10 18 L 16 18 M 10 30 L 16 30 M 10 42 L 16 42" />
            </svg>
            <p>Belum ada riwayat aktivitas pipeline yang tercatat. Riwayat muncul setelah sebuah video masuk ke tahap pipeline.</p>
            <a href="#upload-section" class="btn btn-sm btn-secondary">Ke area unggah</a>
        </div>
    @else
        <ul class="dash-feed-list">
            @foreach($events as $e)
                @php
                    [$tagClass, $markOn, $label] = match($e->status) {
                        'done' => ['tag', true, 'Selesai'],
                        'running' => ['tag', false, 'Berjalan'],
                        'queued' => ['tag tag-dashed', false, 'Dalam Antrian'],
                        'failed' => ['tag tag-dashed', false, 'Gagal'],
                        default => ['tag tag-dashed', false, $e->status],
                    };
                    $failed = $e->status === 'failed';
                @endphp

                <li wire:key="pj-{{ $e->id }}" class="dash-feed-item">
                    <div class="row row-wrap between" style="align-items: baseline">
                        <div class="dash-feed-meta">
                            <span class="{{ $tagClass }}">
                                <span class="mark {{ $markOn ? 'mark-on' : '' }} {{ $failed ? 'dash-mark-failed' : '' }}" aria-hidden="true"></span>
                                {{ ucfirst($e->stage) }} · {{ $label }}
                            </span>

                            <span class="dash-feed-video mono">Video #{{ $e->video_id }}</span>

                            <span class="dash-feed-ref truncate">{{ $e->video?->source_ref }}</span>
                        </div>

                        <time class="mono muted" datetime="{{ $e->updated_at?->toIso8601String() }}">
                            {{ $e->updated_at?->diffForHumans() }}
                        </time>
                    </div>

                    @if($failed && $e->last_error)
                        <div class="dash-feed-error mono">
                            <strong>Log Error:</strong> {{ $e->last_error }}
                        </div>
                    @endif
                </li>
            @endforeach
        </ul>
    @endif
</div>
