<?php

namespace App\Livewire;

use App\Models\ClipCandidate;
use App\Models\Video;
use App\Services\ClipReviewService;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class ReviewVideo extends Component
{
    public Video $video;

    /** On-screen CTA to burn onto approved clips (campaign requirement). */
    public string $ctaText = '';

    public ?string $flash = null;

    public ?string $error = null;

    // --- State Editor ---
    public ?int $editingId = null; // null: idle, -1: new custom, >0: editing existing
    public int $editStartMs = 0;
    public int $editEndMs = 0;
    public int $editHookScore = 80;
    public string $editRationale = '';

    public string $captionStyle = 'default';

    public int $captionMarginV = 320;

    public string $layout = 'single';

    // --- Caption Mood & Customizer State (Ref: clip_caption.png) ---
    public string $burnSubtitles = 'on'; // 'on' | 'off'
    public string $subtitleColor = 'yellow'; // yellow, pink, orange, white, split_yellow, split_orange, glow
    public bool $glowEffect = false;
    public string $captionAnimation = 'rise'; // pop, fade, rise, drop, zoom, blur, type, off
    public int $captionFontSize = 76;
    public int $captionPosY = 960;
    public int $captionPosX = 540;

    // --- Format & Orientation Selector State (Ref: orient.webp) ---
    public string $renderFormat = 'face_916'; // face_916, blur_916, original_916, square_11, landscape_169


    /** Preset CTA options from the campaign brief; operator can also type one. */
    public function ctaPresets(): array
    {
        return [
            "IT'S OUT. IT'S ACTUALLY OUT.",
            "we got new Backyard Baseball before GTA 6 💀",
            "Pablo Sanchez is back and he's still HIM",
            "bought my childhood back on Steam today",
            "dropped everything to play Backyard Baseball. no regrets",
        ];
    }

    public function mount(Video $video): void
    {
        $this->video = $video;
        // Seed with the configured default CTA so the field isn't empty.
        $this->ctaText = (string) config('autoclip.render.cta_text', '');
        $this->captionStyle = (string) config('autoclip.render.caption_style', 'default');
    }

    public function selectCandidate(int $id): void
    {
        $candidate = $this->candidate($id);
        if (!$candidate) return;

        $this->editingId = $candidate->id;
        $this->editStartMs = $candidate->start_ms;
        $this->editEndMs = $candidate->end_ms;
        $this->editHookScore = $candidate->hook_score;
        $this->editRationale = $candidate->score_rationale ?? '';
        
        $this->dispatch('candidate-selected', [
            'startMs' => $this->editStartMs,
            'endMs' => $this->editEndMs
        ]);
    }

    public function closeEditor(): void
    {
        $this->editingId = null;
        $this->error = null;
    }

    public function saveCandidate(): void
    {
        if (!$this->editingId) return;

        $candidate = $this->candidate($this->editingId);
        if (!$candidate) return;

        if ($this->editStartMs >= $this->editEndMs) {
            $this->error = "Waktu mulai harus lebih kecil dari waktu selesai.";
            return;
        }

        $candidate->update([
            'start_ms' => $this->editStartMs,
            'end_ms' => $this->editEndMs,
            'hook_score' => $this->editHookScore,
            'score_rationale' => $this->editRationale,
        ]);

        $this->error = null;
        $this->dispatch('toast', message: "Klip #{$candidate->id} berhasil diperbarui.", type: 'success');
        $this->closeEditor();
    }

    public function createCustomCandidate(): void
    {
        $this->editingId = -1; // -1 represents new custom clip
        $this->editStartMs = 0;
        $this->editEndMs = min(30000, (int)(($this->video->duration_seconds ?? 0) * 1000));
        $this->editHookScore = 100;
        $this->editRationale = 'Klip kustom dibuat secara manual';
        
        $this->dispatch('candidate-selected', [
            'startMs' => $this->editStartMs,
            'endMs' => $this->editEndMs
        ]);
    }

    public function saveCustomCandidate(): void
    {
        if ($this->editStartMs >= $this->editEndMs) {
            $this->error = "Waktu mulai harus lebih kecil dari waktu selesai.";
            $this->dispatch('toast', message: "Waktu mulai harus lebih kecil dari waktu selesai.", type: 'error');
            return;
        }

        $candidate = $this->video->clipCandidates()->create([
            'start_ms' => $this->editStartMs,
            'end_ms' => $this->editEndMs,
            'hook_score' => $this->editHookScore,
            'score_rationale' => $this->editRationale,
            'status' => ClipCandidate::STATUS_PENDING,
        ]);

        $this->error = null;
        $this->dispatch('toast', message: "Klip kustom baru #{$candidate->id} berhasil dibuat.", type: 'success');
        $this->closeEditor();
    }

    public function approve(int $candidateId, ClipReviewService $review): void
    {
        $candidate = $this->candidate($candidateId);
        if (! $candidate) {
            return;
        }

        try {
            $export = $review->approve(
                $candidate,
                $this->ctaText,
                $this->captionStyle,
                $this->captionMarginV,
                $this->layout
            );
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();
            $this->dispatch('toast', message: $e->getMessage(), type: 'error');

            return;
        }

        $this->error = null;
        $this->dispatch('toast', message: "Klip #{$candidate->id} disetujui — render dimulai (export #{$export->id}).", type: 'success');
    }

    public function reject(int $candidateId, ClipReviewService $review): void
    {
        $candidate = $this->candidate($candidateId);
        if (! $candidate) {
            return;
        }

        $review->reject($candidate);
        $this->error = null;
        $this->dispatch('toast', message: "Klip #{$candidate->id} ditolak.", type: 'warning');
    }

    public function batchRenderAll(ClipReviewService $review): void
    {
        $candidates = $this->video->clipCandidates()
            ->whereIn('status', [ClipCandidate::STATUS_PENDING, ClipCandidate::STATUS_REJECTED])
            ->get();

        if ($candidates->isEmpty()) {
            $this->dispatch('toast', message: "Tidak ada klip pending untuk di-render.", type: 'info');
            return;
        }

        $count = 0;
        foreach ($candidates as $c) {
            try {
                $review->approve(
                    $c,
                    $this->ctaText,
                    $this->captionStyle,
                    $this->captionMarginV,
                    $this->layout
                );
                $count++;
            } catch (\Throwable $e) {}
        }

        $this->dispatch('toast', message: "{$count} klip berhasil dimasukkan ke antrean render batch!", type: 'success');
    }

    /** Guard: only candidates belonging to THIS video can be acted on. */
    private function candidate(int $id): ?ClipCandidate

    {
        return $this->video->clipCandidates()->whereKey($id)->first();
    }

    public function render()
    {
        $candidates = $this->video->clipCandidates()
            ->orderByDesc('hook_score')
            ->get();

        // Keep polling while any candidate is still being rendered.
        $poll = $candidates->contains(
            fn (ClipCandidate $c) => $c->status === ClipCandidate::STATUS_APPROVED
        );

        return view('livewire.review-video', [
            'candidates' => $candidates,
            'poll' => $poll,
        ]);
    }
}
