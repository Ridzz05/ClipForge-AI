<div class="stack">
<style>
    .prj-stats { display: grid; gap: var(--s-4); grid-template-columns: repeat(auto-fit, minmax(min(100%, 200px), 1fr)); }
    .prj-stat { display: flex; flex-direction: column; gap: var(--s-1); }
    .prj-stat-num { font-size: 2rem; font-weight: 700; line-height: 1.1; }
    .prj-stat-label { color: var(--muted-ink); font-size: 0.875rem; }
    .prj-name { font-weight: 700; overflow-wrap: anywhere; }
    .prj-num { text-align: right; white-space: nowrap; }
    .prj-table { min-width: 560px; }
</style>

    <div class="page-head rise" style="--i:0">
        <div class="row row-wrap between">
            <h1>Proyek</h1>
            <button type="button" class="btn btn-secondary"
                    wire:click="cleanWorkFiles"
                    wire:confirm="Hapus semua berkas kerja (potongan segmen, caption .ass, file sementara) dari ekspor yang sudah selesai? Klip hasil render tidak disentuh."
                    @disabled($usage['reclaimable_bytes'] === 0)>
                Bersihkan berkas kerja
            </button>
        </div>
        <p>Pemakaian disk per video. Berkas kerja adalah sisa render sementara; aman dihapus dan klip final tidak ikut terhapus.</p>
    </div>

    <div class="panel rise" style="--i:1">
        <div class="prj-stats">
            <div class="prj-stat">
                <span class="prj-stat-num">{{ count($usage['videos']) }}</span>
                <span class="prj-stat-label">Video diproses</span>
            </div>
            <div class="prj-stat">
                <span class="prj-stat-num">{{ \App\Livewire\Projects::bytes($usage['total_bytes']) }}</span>
                <span class="prj-stat-label">Disk terpakai</span>
            </div>
            <div class="prj-stat">
                <span class="prj-stat-num">{{ \App\Livewire\Projects::bytes($usage['reclaimable_bytes']) }}</span>
                <span class="prj-stat-label">Bisa dibebaskan</span>
            </div>
        </div>
    </div>

    @if(empty($usage['videos']))
        <div class="empty rise" style="--i:2">
            <p>Belum ada video. Unggah di Dashboard untuk mulai.</p>
            <a href="/" wire:navigate class="btn btn-secondary">Buka Dashboard</a>
        </div>
    @else
        <div class="panel rise" style="--i:2">
            <div class="table-wrap">
                <table class="prj-table">
                    <thead>
                        <tr>
                            <th scope="col">Video</th>
                            <th scope="col" class="prj-num">Sumber</th>
                            <th scope="col" class="prj-num">Ekspor</th>
                            <th scope="col" class="prj-num">Berkas kerja</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($usage['videos'] as $v)
                            <tr wire:key="prj-{{ $v['id'] }}">
                                <td>
                                    <div class="prj-name">{{ $v['title'] }}</div>
                                    <div class="muted mono">Video #{{ $v['id'] }} · {{ $v['status'] }}</div>
                                </td>
                                <td class="prj-num mono">{{ \App\Livewire\Projects::bytes($v['source_bytes']) }}</td>
                                <td class="prj-num mono">{{ \App\Livewire\Projects::bytes($v['export_bytes']) }}</td>
                                <td class="prj-num mono">{{ \App\Livewire\Projects::bytes($v['reclaimable_bytes']) }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
    @endif
</div>
