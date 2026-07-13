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

    public ?string $flash = null;

    public ?string $error = null;

    /** Route-model-bound in the route definition. */
    public function mount(Video $video): void
    {
        $this->video = $video;
    }

    public function approve(int $candidateId, ClipReviewService $review): void
    {
        $candidate = $this->candidate($candidateId);
        if (! $candidate) {
            return;
        }

        try {
            $export = $review->approve($candidate);
        } catch (\RuntimeException $e) {
            $this->error = $e->getMessage();

            return;
        }

        $this->error = null;
        $this->flash = "Klip #{$candidate->id} disetujui — render dimulai (export #{$export->id}).";
    }

    public function reject(int $candidateId, ClipReviewService $review): void
    {
        $candidate = $this->candidate($candidateId);
        if (! $candidate) {
            return;
        }

        $review->reject($candidate);
        $this->error = null;
        $this->flash = "Klip #{$candidate->id} ditolak.";
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
