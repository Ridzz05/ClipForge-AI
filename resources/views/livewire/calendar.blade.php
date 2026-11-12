<div class="stack">
<style>
    .cal-wrap {
        display: grid;
        grid-template-columns: minmax(0, 2fr) minmax(0, 1fr);
        gap: var(--s-5);
        align-items: start;
    }
    .cal-main { display: flex; flex-direction: column; gap: var(--s-4); min-width: 0; }

    .cal-nav { display: flex; align-items: center; justify-content: space-between; gap: var(--s-3); flex-wrap: wrap; }
    .cal-nav-month { display: flex; align-items: baseline; gap: var(--s-3); flex-wrap: wrap; min-width: 0; }
    .cal-nav-month h2 { font-size: 1.5rem; line-height: 1.2; margin: 0; }
    .cal-nav-btns { display: flex; gap: var(--s-2); flex-wrap: wrap; }
    .cal-nav-btns .btn-sm { min-width: 44px; }

    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, minmax(0, 1fr));
        border-top: 1.5px solid var(--ink);
        border-left: 1px solid var(--border-soft);
        background: var(--card);
        border-radius: var(--r);
        overflow: hidden;
    }
    .cal-head {
        font-weight: 700; font-size: 0.875rem; color: var(--graphite);
        padding: var(--s-2);
        border-right: 1px solid var(--border-soft);
        border-bottom: 1.5px solid var(--ink);
        text-align: center;
    }
    .cal-cell {
        min-height: 104px;
        padding: var(--s-2);
        border-right: 1px solid var(--border-soft);
        border-bottom: 1px solid var(--border-soft);
        display: flex; flex-direction: column; gap: 4px;
        min-width: 0;
    }
    .cal-cell-pad { background: var(--paper-2); }
    .cal-cell-today { background: color-mix(in srgb, var(--hl) 28%, var(--card)); box-shadow: inset 0 0 0 2px var(--ink); }
    .cal-daynum { display: flex; align-items: center; gap: 4px; flex-wrap: wrap; font-variant-numeric: tabular-nums; }
    .cal-daynum .mono { font-weight: 700; color: var(--ink); }
    .cal-today-tag { font-size: 0.75rem; font-weight: 700; display: inline-flex; align-items: center; gap: 3px; }

    .cal-post {
        display: block; width: 100%;
        text-align: left;
        background: transparent;
        border: 1px solid var(--border-soft);
        border-radius: var(--r);
        padding: 2px var(--s-2);
        font: inherit; color: inherit;
        cursor: pointer;
        min-height: 44px;
    }
    .cal-post:hover { border-color: var(--ink); background: var(--paper-2); }
    .cal-post[aria-pressed="true"] { border-color: var(--ink); background: var(--hl); }
    .cal-post-line { display: flex; align-items: baseline; gap: var(--s-2); flex-wrap: wrap; min-width: 0; }
    .cal-post-time { font-weight: 700; }
    .cal-post-platform { font-size: 0.8125rem; color: var(--graphite); overflow-wrap: anywhere; }
    .cal-post-label { display: block; font-size: 0.75rem; color: var(--muted-ink); overflow-wrap: anywhere; }
    .cal-post-flags { display: flex; gap: 3px; flex-wrap: wrap; margin-top: 2px; }
    .cal-flag { font-size: 0.6875rem; }
    .cal-post .tag { padding: 0 6px; font-size: 0.6875rem; }

    .cal-agenda { display: none; }

    .cal-form { position: sticky; top: var(--s-4); }
    .cal-form-head { display: flex; align-items: flex-start; justify-content: space-between; gap: var(--s-2); }
    .cal-selected { display: flex; flex-direction: column; gap: var(--s-2); }
    .cal-download { display: inline-flex; align-items: center; min-height: 44px; }

    .cal-hint { color: var(--muted-ink); font-size: 0.875rem; }

    @media (max-width: 767px) {
        .cal-wrap { grid-template-columns: minmax(0, 1fr); }
        .cal-form { position: static; }
        .cal-grid { display: none; }
        .cal-agenda { display: flex; flex-direction: column; gap: var(--s-3); }
        .cal-agenda-day { border: 1.5px solid var(--border-soft); border-radius: var(--r); padding: var(--s-3); background: var(--card); }
        .cal-agenda-day.is-today { box-shadow: inset 0 0 0 2px var(--ink); }
        .cal-agenda-head { display: flex; align-items: center; gap: var(--s-2); flex-wrap: wrap; margin-bottom: var(--s-2); font-weight: 700; }
        .cal-agenda-posts { display: flex; flex-direction: column; gap: var(--s-2); }
    }
</style>
    <div class="page-head">
        <h1>Kalender Konten</h1>
        <p>Perencana jadwal. ClipForge belum memposting otomatis; unduh klip lalu unggah manual.</p>
    </div>

    @if($exports->isEmpty())
        <div class="empty">
            <svg class="doodle" viewBox="0 0 64 64" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                <path d="M8 14h48v38H8z"/>
                <path d="M8 24h48M20 24v28M38 24v28M20 34h-12M38 34h-18"/>
                <path d="M14 10v8M50 10v8" />
                <path d="M24 44c3-4 7-4 10 0" />
            </svg>
            <p>Belum ada klip yang selesai dirender.</p>
            <p class="small muted">Setujui kandidat klip di halaman review, lalu kembali ke sini untuk menjadwalkannya.</p>
            <a href="/" wire:navigate class="btn btn-secondary">Buka Dashboard</a>
        </div>
    @else
        <div class="cal-wrap">
            <section class="cal-main" aria-label="Kalender bulan {{ $monthLabel }}">
                <div class="cal-nav">
                    <div class="cal-nav-month">
                        <h2>{{ $monthLabel }}</h2>
                        <span class="tag tag-hl small">
                            <span class="mark mark-on" aria-hidden="true"></span>
                            Hari ini: {{ $weekdayLong[$today->dayOfWeekIso - 1] }}, {{ $today->format('j') }} {{ \App\Livewire\Calendar::MONTHS[(int) $today->format('n') - 1] }}
                        </span>
                    </div>
                    <div class="cal-nav-btns">
                        <button type="button" wire:click="previousMonth" class="btn btn-sm btn-secondary" aria-label="Bulan sebelumnya">
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M15 5c-4 3-6 5-7 7 1 2 3 4 7 7"/></svg>
                            Sebelumnya
                        </button>
                        <button type="button" wire:click="currentMonth" class="btn btn-sm btn-secondary">Bulan ini</button>
                        <button type="button" wire:click="nextMonth" class="btn btn-sm btn-secondary" aria-label="Bulan berikutnya">
                            Berikutnya
                            <svg viewBox="0 0 24 24" width="16" height="16" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" aria-hidden="true"><path d="M9 5c4 3 6 5 7 7-1 2-3 4-7 7"/></svg>
                        </button>
                    </div>
                </div>

                <div class="cal-grid" aria-label="Kalender {{ $monthLabel }}">
                    @foreach($weekdayShort as $name)
                        <div class="cal-head" >{{ $name }}</div>
                    @endforeach

                    @foreach($cells as $cell)
                        @if($cell === null)
                            <div class="cal-cell cal-cell-pad" ></div>
                        @else
                            @php($isToday = $cell['day']->isSameDay($today))
                            <div
                                wire:key="cal-day-{{ $cell['day']->format('Y-m-d') }}"
                                class="cal-cell {{ $isToday ? 'cal-cell-today' : '' }}"
                                
                                @if($isToday) aria-current="date" @endif
                            >
                                <div class="cal-daynum">
                                    <span class="mono">{{ $cell['day']->format('j') }}</span>
                                    @if($isToday)
                                        <span class="cal-today-tag"><span class="mark mark-on" aria-hidden="true"></span> Hari ini</span>
                                    @endif
                                </div>

                                @foreach($cell['posts'] as $post)
                                    <button
                                        type="button"
                                        wire:key="cal-post-{{ $post->id }}"
                                        wire:click="selectPost({{ $post->id }})"
                                        class="cal-post"
                                        aria-pressed="{{ $selectedId === $post->id ? 'true' : 'false' }}"
                                        aria-label="Jadwal {{ $post->platform }} pukul {{ $post->scheduled_at->format('H:i') }}"
                                    >
                                        <span class="cal-post-line">
                                            <span class="cal-post-time mono">{{ $post->scheduled_at->format('H:i') }}</span>
                                            <span class="cal-post-platform">{{ $post->platform }}</span>
                                        </span>
                                        <span class="cal-post-label">{{ \Illuminate\Support\Str::limit($post->caption ?: ($post->export?->headline_text ?: 'Klip #'.$post->export_id), 28) }}</span>
                                        <span class="cal-post-flags">
                                            <span class="tag {{ $post->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'tag-solid' : '' }}">{{ $post->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'Sudah diposting' : 'Terjadwal' }}</span>
                                            @if($post->status === \App\Models\ScheduledPost::STATUS_PLANNED && $post->scheduled_at->isPast())
                                                <span class="tag tag-hl">Lewat jadwal</span>
                                            @endif
                                        </span>
                                    </button>
                                @endforeach
                            </div>
                        @endif
                    @endforeach
                </div>

                <div class="cal-agenda" aria-label="Agenda bulan {{ $monthLabel }}">
                    @foreach($agenda as $cell)
                        @php($isToday = $cell['day']->isSameDay($today))
                        <div wire:key="cal-agenda-{{ $cell['day']->format('Y-m-d') }}" class="cal-agenda-day {{ $isToday ? 'is-today' : '' }}">
                            <div class="cal-agenda-head">
                                <span>{{ $weekdayLong[$cell['day']->dayOfWeekIso - 1] }}, {{ $cell['day']->format('j') }} {{ \App\Livewire\Calendar::MONTHS[(int) $cell['day']->format('n') - 1] }}</span>
                                @if($isToday)
                                    <span class="cal-today-tag"><span class="mark mark-on" aria-hidden="true"></span> Hari ini</span>
                                @endif
                            </div>
                            <div class="cal-agenda-posts">
                                @forelse($cell['posts'] as $post)
                                    <button
                                        type="button"
                                        wire:key="cal-agenda-post-{{ $post->id }}"
                                        wire:click="selectPost({{ $post->id }})"
                                        class="cal-post"
                                        aria-pressed="{{ $selectedId === $post->id ? 'true' : 'false' }}"
                                    >
                                        <span class="cal-post-line">
                                            <span class="cal-post-time mono">{{ $post->scheduled_at->format('H:i') }}</span>
                                            <span class="cal-post-platform">{{ $post->platform }}</span>
                                        </span>
                                        <span class="cal-post-label">{{ \Illuminate\Support\Str::limit($post->caption ?: ($post->export?->headline_text ?: 'Klip #'.$post->export_id), 48) }}</span>
                                        <span class="cal-post-flags">
                                            <span class="tag {{ $post->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'tag-solid' : '' }}">{{ $post->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'Sudah diposting' : 'Terjadwal' }}</span>
                                            @if($post->status === \App\Models\ScheduledPost::STATUS_PLANNED && $post->scheduled_at->isPast())
                                                <span class="tag tag-hl">Lewat jadwal</span>
                                            @endif
                                        </span>
                                    </button>
                                @empty
                                    <p class="cal-hint">Tidak ada jadwal.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                @if($monthPosts->isEmpty())
                    <p class="cal-hint">Belum ada jadwal di bulan ini. Pilih klip di panel samping untuk mulai.</p>
                @endif
            </section>

            <aside class="cal-form panel" aria-labelledby="cal-form-title">
                <div class="section-head">
                    <h2 id="cal-form-title">{{ $editing ? 'Ubah jadwal' : 'Jadwalkan klip' }}</h2>
                    @if($editing)
                        <button type="button" wire:click="cancelEdit" class="btn btn-sm btn-quiet">Batal</button>
                    @endif
                </div>

                @if($selected)
                    <div class="cal-selected">
                        <span class="tag {{ $selected->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'tag-solid' : '' }}">
                            {{ $selected->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'Sudah diposting' : 'Terjadwal' }}
                        </span>
                        @if($selected->status === \App\Models\ScheduledPost::STATUS_POSTED && $selected->posted_at)
                            <span class="small muted">Ditandai pada <span class="mono">{{ $selected->posted_at->format('d/m/Y H:i') }}</span></span>
                        @elseif($selected->scheduled_at->isPast())
                            <span class="tag tag-hl">Lewat jadwal</span>
                        @endif
                        @if($selected->export && $selected->export->output_path)
                            <a href="/api/exports/{{ $selected->export->id }}/download" class="btn btn-sm btn-secondary cal-download">Unduh klip</a>
                        @endif
                        <div class="row row-wrap">
                            <button type="button" wire:click="togglePosted" class="btn btn-sm">
                                {{ $selected->status === \App\Models\ScheduledPost::STATUS_POSTED ? 'Tandai belum diposting' : 'Tandai sudah diposting' }}
                            </button>
                            <button
                                type="button"
                                wire:click="delete"
                                wire:confirm="Hapus jadwal ini?"
                                class="btn btn-sm btn-quiet"
                            >Hapus</button>
                        </div>
                    </div>
                    <hr class="rule">
                @endif

                <form wire:submit="save" class="stack" novalidate>
                    <div class="field">
                        <label for="cal-export">Klip</label>
                        <select id="cal-export" wire:model="exportId" aria-describedby="cal-export-hint">
                            <option value="">Pilih klip yang sudah dirender</option>
                            @foreach($exports as $export)
                                @php($title = $export->headline_text ?: ($export->clipCandidate?->video?->source_ref ?? 'Klip'))
                                <option value="{{ $export->id }}">#{{ $export->id }} — {{ \Illuminate\Support\Str::limit($title, 48) }}</option>
                            @endforeach
                        </select>
                        <span id="cal-export-hint" class="field-hint">Hanya klip berstatus selesai dirender.</span>
                        @error('exportId') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="cal-platform">Platform</label>
                        <select id="cal-platform" wire:model="platform">
                            @foreach($platforms as $option)
                                <option value="{{ $option }}">{{ $option }}</option>
                            @endforeach
                        </select>
                        @error('platform') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="cal-when">Tanggal dan jam</label>
                        <input id="cal-when" type="datetime-local" wire:model="scheduledAt" required>
                        <span class="field-hint">Zona waktu: {{ config('app.timezone') }}.</span>
                        @error('scheduledAt') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <div class="field">
                        <label for="cal-caption">Caption</label>
                        <textarea id="cal-caption" wire:model="caption" rows="4" maxlength="2200" placeholder="Teks unggahan, maksimal 2200 karakter."></textarea>
                        @error('caption') <span class="field-error">{{ $message }}</span> @enderror
                    </div>

                    <button type="submit" class="btn btn-block">{{ $editing ? 'Simpan perubahan' : 'Jadwalkan' }}</button>
                </form>
            </aside>
        </div>
    @endif
</div>
