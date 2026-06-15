<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\ResolveScoringSessionClaimRequest;
use App\Http\Requests\Api\V1\StoreScoringSessionClaimRequest;
use App\Http\Resources\Api\V1\ScoringSessionClaimResource;
use App\Models\ScoringSession;
use App\Models\ScoringSessionClaim;
use App\Models\ScoringSessionGroup;
use App\Services\Scoring\ScoringSessionClaimService;
use App\Support\ApiResponse;
use App\Support\Enums\ClaimStatus;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Klaim & approval host — Sprint 13, Phase 2. A signed-in archer claims a guest
 * slot ("Ini Saya"); the host reviews a context-rich inbox and approves/rejects;
 * on approval ownership transfers transactionally (service). Claim submission &
 * inbox are nested under the group; resolving/cancelling a claim hangs off
 * `scoring/claims/{claim}` (matrix §4: host approves, claimant cancels).
 */
class ScoringSessionClaimController extends Controller
{
    public function __construct(private readonly ScoringSessionClaimService $claims) {}

    /**
     * Submit a claim over a guest slot (task 13.1). Any authenticated archer may
     * claim; the host gates it. Idempotency-friendly: a re-claim after a
     * cancel/reject revives the same row, a pending duplicate is rejected.
     */
    public function store(
        StoreScoringSessionClaimRequest $request,
        ScoringSessionGroup $group,
        ScoringSession $session,
    ): JsonResponse {
        $claim = $this->claims->submitClaim(
            $group,
            $session,
            $request->user(),
            $request->validated()['message'] ?? null,
        );

        return ApiResponse::success(
            new ScoringSessionClaimResource($claim->load(['claimant', 'session'])),
            'Klaim diajukan',
            201,
        );
    }

    /**
     * Host inbox — claims to review for a group (task 13.2). Host-only. Optional
     * `?status=` filter (defaults to all); rich slot context per claim.
     */
    public function index(Request $request, ScoringSessionGroup $group): JsonResponse
    {
        abort_unless($request->user()->can('manage', $group), 404, 'Resource not found.');

        $validated = $request->validate([
            'status' => ['nullable', 'string', Rule::enum(ClaimStatus::class)],
        ]);
        $status = isset($validated['status']) ? ClaimStatus::from($validated['status']) : null;

        $claims = $this->claims->hostInbox($group, $status);

        return ApiResponse::success(ScoringSessionClaimResource::collection($claims));
    }

    /**
     * Approve or reject a claim (tasks 13.3/13.5). Host of the claim's group
     * only. Approve transfers ownership + recomputes PB/gamification + auto-
     * rejects the losing claims; reject just resolves this one.
     */
    public function update(ResolveScoringSessionClaimRequest $request, ScoringSessionClaim $claim): JsonResponse
    {
        abort_unless($request->user()->can('manage', $claim->group), 404, 'Resource not found.');

        $claim = $request->validated()['action'] === 'approve'
            ? $this->claims->approveClaim($claim, $request->user())
            : $this->claims->rejectClaim($claim, $request->user());

        return ApiResponse::success(
            new ScoringSessionClaimResource($claim->load(['claimant', 'session'])),
            $claim->status === ClaimStatus::Approved ? 'Klaim disetujui' : 'Klaim ditolak',
        );
    }

    /**
     * Cancel your own pending claim (task 13.5). Claimant-only.
     */
    public function destroy(Request $request, ScoringSessionClaim $claim): JsonResponse
    {
        abort_unless($claim->claimant_user_id === $request->user()->id, 404, 'Resource not found.');

        $this->claims->cancelClaim($claim);

        return ApiResponse::success(null, 'Klaim dibatalkan');
    }
}
