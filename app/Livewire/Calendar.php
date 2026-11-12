<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\Export;
use App\Models\ScheduledPost;
use Carbon\CarbonImmutable;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Calendar extends Component
{
    public const PLATFORMS = [
        'TikTok',
        'Instagram Reels',
        'YouTube Shorts',
        'Facebook Reels',
        'Threads',
        'Pinterest',
        'Bluesky',
        'Lainnya',
    ];

    public const WEEKDAYS = ['Senin', 'Selasa', 'Rabu', 'Kamis', 'Jumat', 'Sabtu', 'Minggu'];

    public const WEEKDAYS_SHORT = ['Sen', 'Sel', 'Rab', 'Kam', 'Jum', 'Sab', 'Min'];

    public const MONTHS = [
        'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
        'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember',
    ];

    /** Displayed month, `Y-m`. */
    public string $month = '';

    public ?int $selectedId = null;

    public bool $editing = false;

    public string $exportId = '';

    public string $platform = 'TikTok';

    public string $scheduledAt = '';

    public string $caption = '';

    public function mount(): void
    {
        $this->month = CarbonImmutable::now($this->tz())->format('Y-m');
    }

    public function updatedMonth(mixed $value): void
    {
        if ($this->parseMonth(is_string($value) ? $value : '') === null) {
            $this->month = CarbonImmutable::now($this->tz())->format('Y-m');
        }
    }

    public function previousMonth(): void
    {
        $this->goToMonth($this->monthStart()->subMonth());
    }

    public function nextMonth(): void
    {
        $this->goToMonth($this->monthStart()->addMonth());
    }

    public function currentMonth(): void
    {
        $this->goToMonth(CarbonImmutable::now($this->tz())->startOfMonth());
    }

    public function selectPost(int $id): void
    {
        $post = ScheduledPost::find($id);

        if ($post === null) {
            return;
        }

        $this->resetValidation();
        $this->selectedId = $post->id;
        $this->editing = true;
        $this->fillForm($post);
    }

    public function cancelEdit(): void
    {
        $this->selectedId = null;
        $this->editing = false;
        $this->exportId = '';
        $this->platform = 'TikTok';
        $this->scheduledAt = '';
        $this->caption = '';
        $this->resetValidation();
    }

    public function save(): void
    {
        $data = $this->validate(
            [
                'exportId' => [
                    'required',
                    'integer',
                    Rule::exists('exports', 'id')
                        ->where('status', Export::STATUS_RENDERED)
                        ->whereNotNull('output_path'),
                ],
                'platform' => ['required', Rule::in(self::PLATFORMS)],
                'scheduledAt' => ['required', 'date_format:Y-m-d\TH:i'],
                'caption' => ['nullable', 'string', 'max:2200'],
            ],
            [
                'exportId.required' => 'Pilih klip yang mau dijadwalkan.',
                'exportId.integer' => 'Klip tidak dikenali.',
                'exportId.exists' => 'Klip itu belum selesai dirender.',
                'platform.required' => 'Pilih platform tujuan.',
                'platform.in' => 'Platform tidak dikenali.',
                'scheduledAt.required' => 'Isi tanggal dan jam.',
                'scheduledAt.date_format' => 'Tanggal tidak valid, pakai format YYYY-MM-DDTHH:MM.',
                'caption.max' => 'Caption maksimal 2200 karakter.',
            ],
        );

        $at = $this->parseDateTime($data['scheduledAt']);

        if ($at === null) {
            $this->addError('scheduledAt', 'Tanggal tidak valid, pakai format YYYY-MM-DDTHH:MM.');

            return;
        }

        $post = $this->selectedId !== null ? ScheduledPost::find($this->selectedId) : null;
        $created = $post === null;

        if ($post === null) {
            $post = new ScheduledPost();
            $post->status = ScheduledPost::STATUS_PLANNED;
        }

        $post->fill([
            'export_id' => (int) $data['exportId'],
            'platform' => $data['platform'],
            'scheduled_at' => $at,
            'caption' => filled($data['caption'] ?? null) ? trim((string) $data['caption']) : null,
        ])->save();

        $this->selectedId = $post->id;
        $this->editing = true;
        $this->month = $post->scheduled_at->format('Y-m');
        $this->fillForm($post);

        $this->dispatch(
            'toast',
            message: $created ? 'Jadwal klip dibuat.' : 'Jadwal klip diperbarui.',
            type: 'success',
        );
    }

    public function togglePosted(): void
    {
        $post = $this->selectedPost();

        if ($post === null) {
            return;
        }

        $wasPosted = $post->status === ScheduledPost::STATUS_POSTED;

        $post->status = $wasPosted ? ScheduledPost::STATUS_PLANNED : ScheduledPost::STATUS_POSTED;
        $post->posted_at = $wasPosted ? null : CarbonImmutable::now($this->tz());
        $post->save();

        $this->fillForm($post);
        $this->dispatch(
            'toast',
            message: $wasPosted ? 'Ditandai belum diposting.' : 'Ditandai sudah diposting.',
            type: 'success',
        );
    }

    public function delete(): void
    {
        $post = $this->selectedPost();

        if ($post === null) {
            return;
        }

        $post->delete();
        $this->cancelEdit();
        $this->dispatch('toast', message: 'Jadwal dihapus.', type: 'success');
    }

    public function render()
    {
        $start = $this->monthStart();
        $end = $start->endOfMonth();

        $monthPosts = ScheduledPost::query()
            ->with('export.clipCandidate.video')
            ->whereBetween('scheduled_at', [$start, $end])
            ->orderBy('scheduled_at')
            ->get();

        $byDay = $monthPosts->groupBy(fn (ScheduledPost $post) => $post->scheduled_at->format('Y-m-d'));

        $cells = [];
        $lead = $start->dayOfWeekIso - 1;

        for ($i = 0; $i < $lead; $i++) {
            $cells[] = null;
        }

        for ($dayNum = 1; $dayNum <= $start->daysInMonth; $dayNum++) {
            $day = $start->setDay($dayNum);
            $cells[] = [
                'day' => $day,
                'posts' => $byDay->get($day->format('Y-m-d'), collect()),
            ];
        }

        while (count($cells) % 7 !== 0) {
            $cells[] = null;
        }

        $todayKey = CarbonImmutable::now($this->tz())->format('Y-m-d');
        $agenda = array_values(array_filter(
            $cells,
            fn ($cell) => $cell !== null && ($cell['posts']->isNotEmpty() || $cell['day']->format('Y-m-d') === $todayKey),
        ));

        $exports = Export::query()
            ->with('clipCandidate.video')
            ->where('status', Export::STATUS_RENDERED)
            ->whereNotNull('output_path')
            ->latest()
            ->limit(100)
            ->get();

        $selected = $this->selectedPost();

        $monthLabel = self::MONTHS[(int) $start->format('n') - 1].' '.$start->format('Y');

        return view('livewire.calendar', [
            'cells' => $cells,
            'agenda' => $agenda,
            'exports' => $exports,
            'selected' => $selected,
            'platforms' => self::PLATFORMS,
            'weekdayShort' => self::WEEKDAYS_SHORT,
            'weekdayLong' => self::WEEKDAYS,
            'monthLabel' => $monthLabel,
            'today' => CarbonImmutable::now($this->tz())->startOfDay(),
            'monthPosts' => $monthPosts,
        ]);
    }

    private function goToMonth(CarbonImmutable $month): void
    {
        $this->month = $month->format('Y-m');
        $this->cancelEdit();
    }

    private function selectedPost(): ?ScheduledPost
    {
        if ($this->selectedId === null) {
            return null;
        }

        return ScheduledPost::query()
            ->with('export.clipCandidate.video')
            ->find($this->selectedId);
    }

    private function fillForm(ScheduledPost $post): void
    {
        $this->exportId = (string) $post->export_id;
        $this->platform = in_array($post->platform, self::PLATFORMS, true) ? $post->platform : 'Lainnya';
        $this->scheduledAt = $post->scheduled_at->format('Y-m-d\TH:i');
        $this->caption = (string) ($post->caption ?? '');
    }

    private function monthStart(): CarbonImmutable
    {
        return $this->parseMonth($this->month)
            ?? CarbonImmutable::now($this->tz())->startOfMonth();
    }

    private function parseMonth(string $value): ?CarbonImmutable
    {
        if (preg_match('/^\d{4}-\d{2}$/', $value) !== 1) {
            return null;
        }

        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m', $value, $this->tz());
        } catch (\Throwable) {
            return null;
        }

        if ($parsed === false || $parsed->format('Y-m') !== $value) {
            return null;
        }

        return $parsed->startOfMonth();
    }

    private function parseDateTime(string $value): ?CarbonImmutable
    {
        try {
            $parsed = CarbonImmutable::createFromFormat('!Y-m-d\TH:i', $value, $this->tz());
        } catch (\Throwable) {
            return null;
        }

        if ($parsed === false || $parsed->format('Y-m-d\TH:i') !== $value) {
            return null;
        }

        return $parsed;
    }

    private function tz(): string
    {
        return (string) config('app.timezone');
    }
}
