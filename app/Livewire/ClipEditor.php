<?php

declare(strict_types=1);

namespace App\Livewire;

use App\Models\ClipCandidate;
use App\Services\ClipReviewService;
use App\Services\TranslationService;
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
    public string $captionAnimation = 'karaoke';
    public int $captionFontSize = 76;
    public int $captionPosY = 960;
    public int $captionPosX = 540;

    // --- Format & Orientation Selector ---
    public string $renderFormat = 'face_916';

    // --- Live Translation State ---
    public string $targetLanguage = 'original';
    public array $clipWords = [];

    public ?string $error = null;

    public function ctaPresets(): array
    {
        return [
            "Follow Halaman Ini untuk Tips Bisnis Rumahan 💡",
            "Share ke Grup FB / WhatsApp Bunda 📩",
            "Komen 'BISA' kalau Bunda Siap Mulai Usaha 💬",
            "Simpan Video Ini untuk Catatan Keuangan Bunda 📌",
            "Follow Halaman Ini untuk Insight Setiap Hari 🔔",
            "Share ke Teman Kamu yang Butuh Ini ↗️",
            "Tulis Pendapatmu di Kolom Komentar 💬",
            "Ikuti Halaman Ini Agar Tidak Ketinggalan 🚀",
        ];
    }


    public function mount(ClipCandidate $candidate): void
    {
        $this->candidate = $candidate->load(['video', 'video.transcript', 'video.transcript.segments']);
        $this->editStartMs = $candidate->start_ms;
        $this->editEndMs = $candidate->end_ms;
        $this->editHookScore = $candidate->hook_score;
        $this->editRationale = $candidate->score_rationale ?? '';
        $this->ctaText = (string) config('autoclip.render.cta_text', '');
        $this->loadClipWords();
    }

    public function updatedTargetLanguage(TranslationService $translation): void
    {
        $rawWords = $this->extractRawWords();
        if ($this->targetLanguage === 'original') {
            $this->clipWords = $rawWords;
        } else {
            $this->clipWords = $translation->translateWords($rawWords, $this->targetLanguage);
        }
        $this->dispatch('words-updated', words: $this->clipWords);
    }

    private function loadClipWords(): void
    {
        $this->clipWords = $this->extractRawWords();
    }

    private function extractRawWords(): array
    {
        $words = [];
        if (!$this->candidate->video || !$this->candidate->video->transcript) {
            return $words;
        }

        $segments = $this->candidate->video->transcript->segments()
            ->where('end_ms', '>=', $this->editStartMs)
            ->where('start_ms', '<=', $this->editEndMs)
            ->orderBy('start_ms')
            ->get();

        foreach ($segments as $seg) {
            $segWords = $seg->words;
            if (is_array($segWords)) {
                foreach ($segWords as $w) {
                    $wStart = isset($w['start_ms']) ? (int) $w['start_ms'] : 0;
                    $wEnd = isset($w['end_ms']) ? (int) $w['end_ms'] : 0;
                    if ($wEnd >= $this->editStartMs && $wStart <= $this->editEndMs) {
                        $words[] = [
                            'word' => (string) ($w['word'] ?? ''),
                            'start_ms' => $wStart,
                            'end_ms' => $wEnd,
                        ];
                    }
                }
            } else {
                $words[] = [
                    'word' => $seg->text,
                    'start_ms' => $seg->start_ms,
                    'end_ms' => $seg->end_ms,
                ];
            }
        }

        return $words;
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
            $styleKey = $this->burnSubtitles === 'off'
                ? 'none'
                : ($this->subtitleColor === 'yellow' ? 'karaoke_yellow' : ($this->subtitleColor === 'pink' ? 'tiktok_green' : 'default'));

            $export = $review->approve(
                $this->candidate,
                $this->ctaText,
                $styleKey,
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
