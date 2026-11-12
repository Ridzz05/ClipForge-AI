<div @if($poll) wire:poll.4s @endif class="stack">
<style>
    .exp-name { font-weight: 700; overflow-wrap: anywhere; }
    .exp-meta {
        color: var(--muted-ink); font-size: 0.875rem; margin-top: 2px;
        display: flex; flex-wrap: wrap; gap: 2px var(--s-3);
    }

    .exp-col-status { width: 160px; }
    .exp-col-act { width: 220px; }

    .exp-error { color: var(--graphite); font-size: 0.875rem; margin-top: var(--s-1); overflow-wrap: anywhere; }
    .exp-error::before { content: "Penyebab: "; font-weight: 700; color: var(--ink); }

    .exp-wait { display: inline-flex; align-items: center; gap: var(--s-2); color: var(--muted-ink); font-size: 0.875rem; }
    .exp-act { display: flex; gap: var(--s-2); justify-content: flex-end; flex-wrap: wrap; }
    .exp-paths { color: var(--graphite); font-size: 0.875rem; }
    .exp-paths a { white-space: nowrap; }
    .exp-table { min-width: 560px; }

    @media (max-width: 760px) {
        .exp-col-status, .exp-col-act { width: auto; }
        .exp-table { min-width: 0; }
        .exp-table thead { display: none; }
        .exp-table tbody, .exp-table tr, .exp-table td { display: block; width: 100%; }
        .exp-table tr { padding: var(--s-4) 0; border-bottom: 1px dashed var(--border-soft); }
        .exp-table td { padding: 0; border-bottom: 0; }
        .exp-table td + td { margin-top: var(--s-3); }
        .exp-act { justify-content: flex-start; }
        .exp-act .btn { width: 100%; }
    }
</style>
    <div class="page-head rise" style="--i:0">
        <div class="row row-wrap between">
            <h1>Klip Ekspor</h1>
            @if($poll)
                <span class="tag"><span class="spinner" aria-hidden="true"></span> Menunggu render selesai</span>
            @endif
        </div>
        <p>Klip vertikal hasil render pipeline. Unduh MP4, atau lanjutkan penyuntingan di OpenReel.</p>
        <p class="exp-paths">
            Klip yang sudah jadi bisa dijadwalkan di <a href="/calendar" wire:navigate>Kalender</a>,
            dengan gaya bawaan dari <a href="/brand" wire:navigate>Brand kit</a>.
        </p>
    </div>

    @if($exports->isEmpty())
        <div class="empty rise" style="--i:1">
            <svg class="doodle" viewBox="0 0 64 64" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round">
                <path d="M14 20 L 50 20 L 50 48 L 14 48 Z" />
                <path d="M32 6 L 32 38" />
                <path d="M22 28 L 32 38 L 42 28" />
                <path d="M18 54 C 25 51, 40 57, 47 53" />
            </svg>
            <p>Belum ada klip yang diekspor. Klip muncul setelah sebuah kandidat disetujui dan render selesai.</p>
            <p>Unggah video di Dashboard, lalu setujui kandidat klip di halaman review.</p>
            <a href="/" wire:navigate class="btn btn-secondary">Buka Dashboard</a>
        </div>
    @else
        <div class="panel rise" style="--i:1">
            <div class="table-wrap">
                <table class="exp-table">
                    <thead>
                        <tr>
                            <th scope="col">Klip</th>
                            <th scope="col" class="exp-col-status">Status</th>
                            <th scope="col" class="exp-col-act">Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($exports as $e)
                            @php
                                $video = $e->clipCandidate?->video;
                                $tagCls = match($e->status) {
                                    'rendered' => 'tag tag-solid',
                                    'failed' => 'tag tag-dashed',
                                    default => 'tag',
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
                                <td>
                                    <div class="exp-name">{{ $video?->source_ref ?? '—' }}</div>
                                    <div class="exp-meta">
                                        <span class="mono">Ekspor #{{ $e->id }}</span>
                                        <span>Video #{{ $video?->id ?? '—' }}</span>
                                        <span>Kandidat #{{ $e->clip_candidate_id }}</span>
                                        <span class="mono">{{ $e->aspect_ratio }}</span>
                                        <span>Watermark {{ $e->watermark_applied ? 'aktif' : 'tidak' }}</span>
                                    </div>
                                </td>
                                <td>
                                    <span class="{{ $tagCls }}">
                                        @if($e->status === 'rendering' || $e->status === 'queued')
                                            <span class="spinner" aria-hidden="true"></span>
                                        @endif
                                        {{ $label }}
                                    </span>
                                    @if($e->status === 'failed' && $e->last_error)
                                        <div class="exp-error">{{ $e->last_error }}</div>
                                    @endif
                                </td>
                                <td class="exp-act">
                                    @if($e->status === 'rendered' && $e->output_path)
                                        <a href="/api/exports/{{ $e->id }}/download" class="btn btn-sm">Unduh MP4</a>
                                        <a href="/openreel/?video_url={{ urlencode('/api/exports/' . $e->id . '/download') }}" target="_blank" rel="noopener" class="btn btn-sm btn-secondary">Edit di OpenReel</a>
                                    @elseif($e->status === 'failed')
                                        <span class="muted">Tidak ada unduhan</span>
                                    @else
                                        <span class="exp-wait"><span class="spinner" aria-hidden="true"></span> Menunggu render</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
