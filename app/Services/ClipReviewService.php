<?php

namespace App\Services;

use App\Jobs\ReframeJob;
use App\Models\BrandKit;
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
        ?string $layout = 'single',
        ?float $manualCropX = null,
        ?string $captionFont = null,
        ?float $splitTopCropX = null,
        ?float $splitBottomCropX = null,
        ?array $segments = null,
        ?string $headlineText = null,
        ?string $headlineStyle = null,
        ?int $musicAssetId = null,
        ?int $musicVolume = null
    ): Export {
        $claimed = ClipCandidate::whereKey($candidate->getKey())
            ->whereIn('status', [ClipCandidate::STATUS_PENDING, ClipCandidate::STATUS_REJECTED])
            ->update(['status' => ClipCandidate::STATUS_APPROVED]);

        if ($claimed === 0) {
            $fresh = $candidate->fresh();

            throw new RuntimeException(match ($fresh?->status) {
                ClipCandidate::STATUS_EXPORTED => 'Candidate already exported.',
                ClipCandidate::STATUS_APPROVED => 'Candidate already approved.',
                default => 'Candidate cannot be approved.',
            });
        }

        $candidate->refresh();

        $brand = BrandKit::current();

        $cta = $ctaText !== null && trim($ctaText) !== ''
            ? trim($ctaText)
            : (trim((string) $brand->cta_text) !== '' ? trim((string) $brand->cta_text) : (string) config('autoclip.render.cta_text', ''));

        $style = $captionStyle !== null && trim($captionStyle) !== ''
            ? trim($captionStyle)
            : (trim((string) $brand->caption_style) !== '' ? trim((string) $brand->caption_style) : (string) config('autoclip.render.caption_style', 'default'));

        $marginV = $captionMarginV !== null
            ? (int) $captionMarginV
            : null;

        $font = $captionFont !== null && trim($captionFont) !== ''
            ? trim($captionFont)
            : (trim((string) $brand->caption_font) !== '' ? trim((string) $brand->caption_font) : 'Outfit');

        $headlineStyle = $headlineStyle !== null && trim($headlineStyle) !== ''
            ? trim($headlineStyle)
            : ($brand->headline_style ?: null);

        $export = Export::create([
            'clip_candidate_id' => $candidate->id,
            'aspect_ratio' => '9:16',
            'caption_style' => $style,
            'caption_font' => $font,
            'layout' => $layout ?? 'single',
            'manual_crop_x' => $manualCropX,
            'split_top_crop_x' => $splitTopCropX ?? 0.25,
            'split_bottom_crop_x' => $splitBottomCropX ?? 0.75,
            'segments' => $segments,
            'cta_text' => $cta,
            'status' => Export::STATUS_QUEUED,
            'caption_margin_v' => $marginV,
            'headline_text' => $headlineText !== null && trim($headlineText) !== '' ? trim($headlineText) : null,
            'headline_style' => $headlineStyle,
            'music_asset_id' => $musicAssetId,
            'music_volume' => $musicVolume ?? 20,
        ]);

        ReframeJob::dispatch($export->id);

        return $export;
    }

    public function reject(ClipCandidate $candidate): void
    {
        $candidate->update(['status' => ClipCandidate::STATUS_REJECTED]);
    }
}
