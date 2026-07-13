<?php

namespace App\Services;

use App\Jobs\ReframeJob;
use App\Models\ClipCandidate;
use App\Models\Export;
use RuntimeException;

/**
 * Shared human-review-gate logic (spec section 4/5.5) used by both the API
 * controller and the Livewire review UI, so approve/reject behaves identically
 * regardless of entry point. Approving a candidate creates an Export and
 * dispatches the reframe/caption render.
 */
class ClipReviewService
{
    /**
     * @return Export the export queued for rendering
     *
     * @throws RuntimeException if the candidate was already exported
     */
    public function approve(ClipCandidate $candidate): Export
    {
        if ($candidate->status === ClipCandidate::STATUS_EXPORTED) {
            throw new RuntimeException('Candidate already exported.');
        }

        $candidate->update(['status' => ClipCandidate::STATUS_APPROVED]);

        $export = Export::create([
            'clip_candidate_id' => $candidate->id,
            'aspect_ratio' => '9:16',
            'caption_style' => (string) config('autoclip.render.caption_style'),
            'status' => Export::STATUS_QUEUED,
        ]);

        ReframeJob::dispatch($export->id);

        return $export;
    }

    public function reject(ClipCandidate $candidate): void
    {
        $candidate->update(['status' => ClipCandidate::STATUS_REJECTED]);
    }
}
