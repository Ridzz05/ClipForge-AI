<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\ClipCandidate;
use App\Services\ClipReviewService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ClipEditor extends Component
{
    public ClipCandidate $candidate;

    // --- State Editor ---
    public int $editStartMs = 0;
    public int $editEndMs = 0;
    public int $editHookScore = 80;
    public string $editRationale = '';
    public string $ctaText = '';

    // --- Caption Customizer State ---
    public string $burnSubtitles = 'on';
    public string $subtitleColor = 'yellow';
    public bool $glowEffect = false;
    public string $captionAnimation = 'rise';
    public int $captionFontSize = 76;
    public int $captionPosY = 960;
    public int $captionPosX = 540;

    // --- Format & Orientation Selector ---
    public string $renderFormat = 'face_916';

    public ?string $error = null;

    public function ctaPresets(): array
    {
        return [
            "Follow Halaman Ini untuk Insight Setiap Hari 🔔",
            "Share ke Teman Kamu yang Butuh Ini ↗️",
            "Tulis Pendapatmu di Kolom Komentar 💬",
            "Ikuti Halaman Ini Agar Tidak Ketinggalan 🚀",
            "IT'S OUT. IT'S ACTUALLY OUT.",
        ];
    }

    public function mount(ClipCandidate $candidate): void

    {
        $this->candidate = $candidate->load(['video', 'video.transcript']);
        $this->editStartMs = $candidate->start_ms;
        $this->editEndMs = $candidate->end_ms;
        $this->editHookScore = $candidate->hook_score;
        $this->editRationale = $candidate->score_rationale ?? '';
        $this->ctaText = (string) config('autoclip.render.cta_text', '');
    }

    public function saveAndApprove(ClipReviewService $review)
    {
        if ($this->editStartMs >= $this->editEndMs) {
            $this->error = "Waktu mulai harus lebih kecil dari waktu selesai.";
            $this->dispatch('toast', message: $this->error, type: 'error');
            return;
        }

        $this->candidate->update([
            'start_ms' => $this->editStartMs,
            'end_ms' => $this->editEndMs,
            'hook_score' => $this->editHookScore,
            'score_rationale' => $this->editRationale,
        ]);

        try {
            $export = $review->approve(
                $this->candidate,
                $this->ctaText,
                $this->subtitleColor === 'yellow' ? 'karaoke_yellow' : ($this->subtitleColor === 'pink' ? 'tiktok_green' : 'default'),
                $this->captionPosY,
                $this->renderFormat === 'face_916' ? 'single' : 'split_gaming'
            );

            $this->dispatch('toast', message: "Klip #{$this->candidate->id} disetujui & sedang diekspor!", type: 'success');
            return redirect()->to('/exports');
        } catch (\Throwable $e) {
            $this->error = $e->getMessage();
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');
        }
    }

    public function render()
    {
        return view('livewire.clip-editor');
    }
}
