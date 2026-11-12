<div class="stack">
<style>
    .bk-layout { display: grid; gap: var(--s-6); align-items: start; }
    @media (min-width: 901px) { .bk-layout { grid-template-columns: minmax(0, 2fr) minmax(0, 1fr); } }

    .bk-fieldset { display: flex; flex-direction: column; gap: var(--s-5); }
    .bk-explain { font-size: 0.875rem; color: var(--muted-ink); line-height: 1.4; }

    .bk-logo {
        display: flex; flex-direction: column; gap: var(--s-3);
        align-items: flex-start;
    }
    .bk-logo-frame {
        width: 100%; min-height: 120px;
        display: flex; align-items: center; justify-content: center;
        padding: var(--s-3);
        border: 1.5px dashed var(--border-ctl); border-radius: var(--r);
        background: var(--card); overflow: hidden;
    }
    .bk-logo-frame img {
        max-width: 100%; max-height: 120px; width: auto; height: auto;
        object-fit: contain; display: block;
    }
    .bk-logo-empty { display: flex; flex-direction: column; align-items: center; gap: var(--s-2); text-align: center; color: var(--muted-ink); }

    .bk-preview {
        display: flex; flex-direction: column; gap: var(--s-2);
        padding: var(--s-3) var(--s-4);
        border: 1px solid var(--border-soft); border-radius: var(--r);
        background: var(--paper-2);
    }
    .bk-preview-label { font-size: 0.75rem; text-transform: uppercase; letter-spacing: .04em; color: var(--muted-ink); }
    .bk-preview .tag { align-self: flex-start; white-space: normal; overflow-wrap: anywhere; }
</style>
    <div class="page-head">
        <h1>Brand kit</h1>
        <p>Default merek untuk setiap ekspor baru: CTA, gaya caption, font, dan gaya headline. Logo brand menempel di sudut kanan bawah klip hasil render.</p>
    </div>

    <div class="bk-layout">
        <div class="panel tape rise" style="--i:0">
            <div class="section-head">
                <h2>Identitas &amp; default render</h2>
                <span class="mono muted">{{ $this->logoPath ? 'logo aktif' : 'tanpa logo' }}</span>
            </div>

            <form wire:submit="save" class="bk-fieldset">
                <div class="field">
                    <label for="bk-name">Nama brand</label>
                    <input id="bk-name" type="text" wire:model="name" maxlength="120"
                           placeholder="cth. Studio Kopi Nusantara" autocomplete="organization">
                    @error('name')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Hanya label internal untuk operator.</span>
                </div>

                <div class="field">
                    <label for="bk-cta">Teks CTA default</label>
                    <input id="bk-cta" type="text" wire:model="ctaText" maxlength="80"
                           placeholder="cth. BELI SEKARANG DI TOKO KAMI">
                    @error('ctaText')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Maks. 80 karakter. Isi otomatis pada setiap ekspor baru; operator masih bisa mengubahnya per kandidat. Kosong = ikuti default sistem.</span>
                </div>

                <div class="field">
                    <label for="bk-caption-style">Gaya caption default</label>
                    <select id="bk-caption-style" wire:model="captionStyle">
                        <option value="">Ikuti default sistem</option>
                        @foreach($styles as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('captionStyle')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Template ASS yang dibakar ke video saat render.</span>
                </div>

                <div class="field">
                    <label for="bk-caption-font">Font caption default</label>
                    <select id="bk-caption-font" wire:model="captionFont">
                        <option value="">Ikuti default sistem (Outfit)</option>
                        @foreach($fonts as $font)
                            <option value="{{ $font }}">{{ $font }}</option>
                        @endforeach
                    </select>
                    @error('captionFont')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Font harus terpasang di server render; daftar ini sama dengan yang ada di editor klip.</span>
                </div>

                <div class="field">
                    <label for="bk-headline-style">Gaya headline default</label>
                    <select id="bk-headline-style" wire:model="headlineStyle">
                        <option value="">Ikuti default sistem</option>
                        @foreach($headlineStyles as $key => $style)
                            <option value="{{ $key }}">{{ $style['label'] }}</option>
                        @endforeach
                    </select>
                    @error('headlineStyle')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Headline (jika diisi per ekspor) tampil 5 detik pertama dengan gaya ini.</span>
                </div>

                @if($ctaText || $captionStyle || $headlineStyle)
                    <div class="bk-preview" aria-label="Pratinjau teks">
                        <span class="bk-preview-label">Pratinjau</span>
                        @if($headlineStyle)
                            <span class="tag tag-solid">{{ $headlineStyles[$headlineStyle]['label'] ?? $headlineStyle }} · headline</span>
                        @endif
                        @if($ctaText)
                            <span class="tag tag-hl">{{ $ctaText }}</span>
                        @endif
                        @if($captionStyle && $captionStyle !== 'none')
                            <span class="tag">{{ $styles[$captionStyle] ?? $captionStyle }}</span>
                        @endif
                    </div>
                @endif

                <div class="row row-wrap">
                    <button type="submit" class="btn" wire:loading.attr="disabled" wire:target="save,logo">
                        <span wire:loading.remove wire:target="save">Simpan brand kit</span>
                        <span wire:loading wire:target="save"><span class="spinner" aria-hidden="true"></span>Menyimpan…</span>
                    </button>
                    @if($logo)
                        <span class="tag tag-dashed">Logo baru menunggu simpan</span>
                    @endif
                </div>
            </form>
        </div>

        <div class="panel panel-quiet rise" style="--i:1">
            <div class="section-head">
                <h2>Logo brand</h2>
            </div>

            <div class="bk-logo">
                <div class="bk-logo-frame">
                    @if($logo)
                        <img src="{{ $logo->temporaryUrl() }}" alt="Pratinjau logo baru" width="240" height="120">
                    @elseif($logoUrl)
                        <img src="{{ $logoUrl }}" alt="Logo brand saat ini" width="240" height="120">
                    @else
                        <div class="bk-logo-empty">
                            <svg class="doodle" viewBox="0 0 48 48" width="56" height="56" aria-hidden="true" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round">
                                <rect x="7" y="12" width="34" height="24" rx="4"/>
                                <path d="M7 20h34"/>
                                <path d="M14 28h9"/>
                            </svg>
                            <span class="small">Belum ada logo. Tanpa logo, watermark dari config yang dipakai.</span>
                        </div>
                    @endif
                </div>

                <div class="field" style="width: 100%">
                    <label for="bk-logo">Unggah logo (PNG, JPG, atau WebP)</label>
                    <input id="bk-logo" type="file" wire:model="logo" accept="image/png,image/jpeg,image/webp">
                    @error('logo')<span class="field-error" role="alert">{{ $message }}</span>@enderror
                    <span class="field-hint">Maks. 2 MB. Disarankan PNG transparan. Logo lama dihapus saat logo baru tersimpan.</span>
                </div>

                @if($logo)
                    <span class="small muted">Logo baru akan menggantikan logo aktif setelah kamu menekan Simpan brand kit.</span>
                @endif

                @if($logoPath && ! $logo)
                    <button type="button" class="btn btn-sm btn-secondary"
                            wire:click="removeLogo"
                            wire:confirm="Hapus logo brand? Klip berikutnya kembali memakai watermark dari config."
                            wire:loading.attr="disabled" wire:target="removeLogo">
                        <span wire:loading.remove wire:target="removeLogo">Hapus logo</span>
                        <span wire:loading wire:target="removeLogo"><span class="spinner" aria-hidden="true"></span>Menghapus…</span>
                    </button>
                @endif

                <hr class="rule" style="width: 100%">

                <p class="bk-explain">Logo ditempel otomatis di kanan bawah setiap klip yang dikirim, menggantikan watermark PNG dari config render.</p>
            </div>
        </div>
    </div>
</div>
