<?php

namespace App\Http\Controllers;

use App\Jobs\ReframeJob;
use App\Models\ClipCandidate;
use App\Models\Export;
use Illuminate\Http\JsonResponse;

/**
 * Human review gate (spec section 4/5.5). A candidate must be explicitly
 * approved before any render happens — matching the "Rizki as reviewer" role
 * rather than autonomous publishing. Approval creates an Export row and
 * dispatches the reframe/caption job.
 */
class ClipCandidateReviewController extends Controller
{
    public function approve(ClipCandidate $candidate): JsonResponse
    {
        if ($candidate->status === ClipCandidate::STATUS_EXPORTED) {
            return response()->json(['message' => 'Candidate already exported.'], 409);
        }

        $candidate->update(['status' => ClipCandidate::STATUS_APPROVED]);

        $export = Export::create([
            'clip_candidate_id' => $candidate->id,
            'aspect_ratio' => '9:16',
            'caption_style' => (string) config('autoclip.render.caption_style'),
            'status' => Export::STATUS_QUEUED,
        ]);

        ReframeJob::dispatch($export->id);

        return response()->json([
            'candidate_id' => $candidate->id,
            'status' => $candidate->status,
            'export_id' => $export->id,
        ], 202);
    }

    public function reject(ClipCandidate $candidate): JsonResponse
    {
        $candidate->update(['status' => ClipCandidate::STATUS_REJECTED]);

        return response()->json([
            'candidate_id' => $candidate->id,
            'status' => $candidate->status,
        ]);
    }
}
