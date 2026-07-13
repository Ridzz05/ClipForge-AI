<?php

namespace App\Http\Controllers;

use App\Models\ClipCandidate;
use App\Services\ClipReviewService;
use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Human review gate (spec section 4/5.5). A candidate must be explicitly
 * approved before any render happens — matching the "Rizki as reviewer" role
 * rather than autonomous publishing. Delegates to ClipReviewService so the API
 * and the Livewire UI share one implementation.
 */
class ClipCandidateReviewController extends Controller
{
    public function __construct(private readonly ClipReviewService $review) {}

    public function approve(ClipCandidate $candidate): JsonResponse
    {
        try {
            $export = $this->review->approve($candidate);
        } catch (RuntimeException $e) {
            return response()->json(['message' => $e->getMessage()], 409);
        }

        return response()->json([
            'candidate_id' => $candidate->id,
            'status' => $candidate->status,
            'export_id' => $export->id,
        ], 202);
    }

    public function reject(ClipCandidate $candidate): JsonResponse
    {
        $this->review->reject($candidate);

        return response()->json([
            'candidate_id' => $candidate->id,
            'status' => $candidate->status,
        ]);
    }
}
