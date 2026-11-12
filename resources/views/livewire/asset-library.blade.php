<div class="stack">
<style>
    .lib-layout { display: grid; gap: var(--s-6); align-items: start; }
    @media (min-width: 901px) { .lib-layout { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }

    .lib-upload { display: flex; flex-direction: column; gap: var(--s-2); }
    .lib-upload .row { gap: var(--s-2); }
    .lib-hint { font-size: 0.875rem; color: var(--muted-ink); }

    .lib-list { display: flex; flex-direction: column; gap: var(--s-3); margin: 0; padding: 0; list-style: none; }

    .lib-row {
        display: flex; align-items: center; gap: var(--s-3);
        padding: var(--s-3);
        border: 1px solid var(--border-soft); border-radius: var(--r);
        background: var(--card);
        min-width: 0;
    }
    .lib-row-main { display: flex; flex-direction: column; gap: 2px; min-width: 0; flex: 1 1 auto; }
    .lib-row-name { font-weight: 650; overflow-wrap: anywhere; }
    .lib-meta { display: flex; flex-wrap: wrap; gap: 2px var(--s-3); font-size: 12px; color: var(--muted-ink); }

    .lib-audio { width: 100%; max-width: 100%; height: 40px; margin-top: var(--s-2); }
    .lib-audio-row { display: flex; flex-direction: column; flex: 1 1 auto; min-width: 0; }

    .lib-thumb {
        width: 56px; height: 56px; flex: none;
        border: 1.5px solid var(--border-soft); border-radius: var(--r);
        overflow: hidden; background: var(--paper-2);
    }
    .lib-thumb img { width: 100%; height: 100%; object-fit: cover; display: block; }

    .lib-del { flex: none; }
    .lib-del .spinner { width: 20px; }
</style>
    <div class="page-head">
        <h1>Aset</h1>
        <p>Musik latar dan gambar pendukung untuk render. Musik dipilih per ekspor di halaman review; logo brand diatur di Brand kit.</p>
    </div>

    <div class="lib-layout">
        <section class="panel tape rise" style="--i:0" aria-labelledby="lib-music-title">
            <div class="section-head">
                <h2 id="lib-music-title">Musik</h2>
                <span class="mono muted">{{ $musicAssets->count() }} berkas</span>
            </div>

            <form wire:submit="uploadMusic" class="lib-upload" style="margin-bottom: var(--s-5)">
                <label for="lib-music-input">Unggah musik latar</label>
                <div class="row row-wrap">
                    <input id="lib-music-input" type="file" wire:model="music"
                           accept="{{ collect($musicMimes)->map(fn ($m) => '.'.$m)->implode(',') }}"
                           class="grow">
                    <button type="submit" class="btn btn-sm" wire:loading.attr="disabled" wire:target="uploadMusic,music">
                        <span wire:loading.remove wire:target="uploadMusic">Unggah</span>
                        <span wire:loading wire:target="uploadMusic"><span class="spinner" aria-hidden="true"></span>Mengunggah…</span>
                    </button>
                </div>
                @error('music')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                <span class="lib-hint">Format: {{ strtoupper(implode(', ', $musicMimes)) }} · maks. {{ round($maxSizeKb / 1024) }} MB.</span>
            </form>

            @if($musicAssets->isEmpty())
                <div class="empty">
                    <svg class="doodle" viewBox="0 0 48 48" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                        <path d="M17 34.5V12l16-3v22"/>
                        <ellipse cx="11.5" cy="35" rx="5.5" ry="4" />
                        <ellipse cx="27.5" cy="31" rx="5.5" ry="4" />
                        <path d="M17 17l16-3"/>
                        <path d="M9 9l-3-3M39 9l3-3"/>
                        <path d="M8 22h-4M44 22h-4"/>
                    </svg>
                    <p>Belum ada musik di perpustakaan.</p>
                    <p class="small">Ekspor tanpa musik akan memakai audio asli video saja. Unggah berkas lewat kontrol di atas, lalu pilih musiknya saat menyetujui kandidat.</p>
                </div>
            @else
                <ol class="lib-list">
                    @foreach($musicAssets as $asset)
                        <li class="lib-row rise" style="--i:{{ $loop->index }}" wire:key="music-{{ $asset->id }}">
                            <div class="lib-audio-row">
                                <div class="lib-row-main">
                                    <span class="lib-row-name">{{ $asset->name }}</span>
                                    <span class="lib-meta">
                                        <span>{{ $asset->size_bytes >= 1048576
                                            ? number_format($asset->size_bytes / 1048576, 1, ',', '.') . ' MB'
                                            : number_format($asset->size_bytes / 1024, 0, ',', '.') . ' KB' }}</span>
                                        <span>{{ $asset->duration_seconds !== null
                                            ? sprintf('%d:%02d', intdiv($asset->duration_seconds, 60), $asset->duration_seconds % 60).' menit'
                                            : 'Durasi tidak terbaca' }}</span>
                                        <span class="mono">#{{ $asset->id }}</span>
                                    </span>
                                </div>
                                <audio class="lib-audio" controls preload="none" src="{{ route('assets.file', $asset) }}"></audio>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary lib-del"
                                    wire:click="deleteAsset({{ $asset->id }})"
                                    wire:confirm="Hapus musik ini? Berkas di disk ikut terhapus dan ekspor yang memakainya kembali tanpa musik."
                                    wire:loading.attr="disabled" wire:target="deleteAsset({{ $asset->id }})">
                                <span wire:loading.remove wire:target="deleteAsset({{ $asset->id }})">Hapus</span>
                                <span wire:loading wire:target="deleteAsset({{ $asset->id }})"><span class="spinner" aria-hidden="true"></span></span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>

        <section class="panel panel-quiet rise" style="--i:1" aria-labelledby="lib-image-title">
            <div class="section-head">
                <h2 id="lib-image-title">Gambar</h2>
                <span class="mono muted">{{ $imageAssets->count() }} berkas</span>
            </div>

            <form wire:submit="uploadImage" class="lib-upload" style="margin-bottom: var(--s-5)">
                <label for="lib-image-input">Unggah gambar</label>
                <div class="stack" style="gap: var(--s-2)">
                    <input id="lib-image-input" type="file" wire:model="image"
                           accept="{{ collect($imageMimes)->map(fn ($m) => '.'.$m)->implode(',') }}">
                    <button type="submit" class="btn btn-sm" wire:loading.attr="disabled" wire:target="uploadImage,image">
                        <span wire:loading.remove wire:target="uploadImage">Unggah</span>
                        <span wire:loading wire:target="uploadImage"><span class="spinner" aria-hidden="true"></span>Mengunggah…</span>
                    </button>
                </div>
                @error('image')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                <span class="lib-hint">Format: {{ strtoupper(implode(', ', $imageMimes)) }} · maks. {{ round($maxSizeKb / 1024) }} MB.</span>
            </form>

            @if($imageAssets->isEmpty())
                <div class="empty">
                    <svg class="doodle" viewBox="0 0 48 48" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                        <rect x="6" y="9" width="36" height="30" rx="3"/>
                        <path d="M6 31l10-9 8 7 6-5 12 10"/>
                        <circle cx="17" cy="18" r="3"/>
                    </svg>
                    <p>Belum ada gambar.</p>
                    <p class="small">Tanpa gambar, render tetap berjalan. Unggah lewat kontrol di atas untuk dipakai sebagai bahan tambahan.</p>
                </div>
            @else
                <ol class="lib-list">
                    @foreach($imageAssets as $asset)
                        <li class="lib-row rise" style="--i:{{ $loop->index }}" wire:key="image-{{ $asset->id }}">
                            <div class="lib-thumb">
                                <img src="{{ route('assets.file', $asset) }}" alt="{{ $asset->name }}" width="56" height="56" loading="lazy">
                            </div>
                            <div class="lib-row-main">
                                <span class="lib-row-name">{{ $asset->name }}</span>
                                <span class="lib-meta">
                                    <span>{{ number_format($asset->size_bytes / 1024, 0, ',', '.') }} KB</span>
                                    <span class="mono">#{{ $asset->id }}</span>
                                </span>
                            </div>
                            <button type="button" class="btn btn-sm btn-secondary lib-del"
                                    wire:click="deleteAsset({{ $asset->id }})"
                                    wire:confirm="Hapus gambar ini? Berkas di disk ikut terhapus."
                                    wire:loading.attr="disabled" wire:target="deleteAsset({{ $asset->id }})">
                                <span wire:loading.remove wire:target="deleteAsset({{ $asset->id }})">Hapus</span>
                                <span wire:loading wire:target="deleteAsset({{ $asset->id }})"><span class="spinner" aria-hidden="true"></span></span>
                            </button>
                        </li>
                    @endforeach
                </ol>
            @endif
        </section>
    </div>
</div>
