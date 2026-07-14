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
     * @param  string|null  $ctaText  optional on-screen CTA burned onto the clip;
     *                                 falls back to the configured default when null
     * @return Export the export queued for rendering
     *
     * @throws RuntimeException if the candidate was already exported
     */
    public function approve(
        ClipCandidate $candidate,
        ?string $ctaText = null,
        ?string $captionStyle = null,
        ?int $captionMarginV = null,
        ?string $layout = 'single'
    ): Export {
        if ($candidate->status === ClipCandidate::STATUS_EXPORTED) {
            throw new RuntimeException('Candidate already exported.');
        }

        if ($candidate->status === ClipCandidate::STATUS_APPROVED) {
            throw new RuntimeException('Candidate already approved.');
        }

        $candidate->update(['status' => ClipCandidate::STATUS_APPROVED]);

        $cta = $ctaText !== null && trim($ctaText) !== ''
            ? trim($ctaText)
            : (string) config('autoclip.render.cta_text', '');

        $style = $captionStyle !== null && trim($captionStyle) !== ''
            ? trim($captionStyle)
            : (string) config('autoclip.render.caption_style', 'default');

        $marginV = $captionMarginV !== null
            ? (int) $captionMarginV
            : null;

        $export = Export::create([
            'clip_candidate_id' => $candidate->id,
            'aspect_ratio' => '9:16',
            'caption_style' => $style,
            'layout' => $layout ?? 'single',
            'cta_text' => $cta,
            'status' => Export::STATUS_QUEUED,
            'caption_margin_v' => $marginV,
        ]);

        ReframeJob::dispatch($export->id);

        return $export;
    }

    public function reject(ClipCandidate $candidate): void
    {
        $candidate->update(['status' => ClipCandidate::STATUS_REJECTED]);
    }
}
