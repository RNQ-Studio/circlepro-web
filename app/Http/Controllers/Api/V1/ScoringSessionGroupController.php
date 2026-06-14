<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\AddGroupParticipantsRequest;
use App\Http\Requests\Api\V1\ScoreGroupParticipantRequest;
use App\Http\Requests\Api\V1\StoreScoringSessionGroupRequest;
use App\Http\Requests\Api\V1\SyncGroupParticipantsRequest;
use App\Http\Requests\Api\V1\UpdateScoringSessionGroupRequest;
use App\Http\Resources\Api\V1\GroupParticipantResource;
use App\Http\Resources\Api\V1\ScoringSessionGroupResource;
use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Services\Scoring\ScoringSessionGroupService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Latihan Bersama (group scoring) lifecycle API — Sprint 02, Phase 0. The
 * group is a binder over individual scoring_sessions rows (§1); this controller
 * handles create/list/detail/lookup, batch quick-add of guests, removal and
 * finish/abandon. Score input, sync and leaderboard arrive in Sprint 03.
 */
class ScoringSessionGroupController extends Controller
{
    public function __construct(private readonly ScoringSessionGroupService $groups) {}

    /**
     * Groups the caller hosts or participates in (task 2.2).
     */
    public function index(Request $request): JsonResponse
    {
        $userId = $request->user()->id;
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $groups = ScoringSessionGroup::query()
            ->with('host')
            ->withCount('participants')
            ->where(function ($query) use ($userId): void {
                $query->where('host_user_id', $userId)
                    ->orWhereHas('participants', fn ($p) => $p->where('user_id', $userId));
            })
            ->orderByDesc('started_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ScoringSessionGroupResource::collection($groups));
    }

    /**
     * Create a group + unique join_code; caller becomes host (task 2.1).
     */
    public function store(StoreScoringSessionGroupRequest $request): JsonResponse
    {
        $group = $this->groups->createGroup($request->user(), $request->validated());

        return ApiResponse::success(
            new ScoringSessionGroupResource($group->load('host')),
            'Group created',
            201,
        );
    }

    /**
     * Group detail with roster — host or participant only (task 2.2).
     */
    public function show(Request $request, ScoringSessionGroup $group): JsonResponse
    {
        abort_unless($request->user()->can('view', $group), 404, 'Resource not found.');

        $group->load(['host', 'participants.user'])->loadCount('participants');

        return ApiResponse::success(new ScoringSessionGroupResource($group));
    }

    /**
     * Preview a group by its join code before joining (task 2.3). Shows the full
     * round format but not the full roster (light privacy).
     */
    public function lookup(Request $request): JsonResponse
    {
        $validated = $request->validate(['code' => ['required', 'string', 'max:12']]);
        $code = Str::upper(trim($validated['code']));

        $group = ScoringSessionGroup::query()
            ->with('host')
            ->withCount('participants')
            ->where('join_code', $code)
            ->first();

        abort_if($group === null, 404, 'Resource not found.');

        return ApiResponse::success(new ScoringSessionGroupResource($group));
    }

    /**
     * Edit title/format (while no score exists) or finish/abandon (task 2.6).
     */
    public function update(UpdateScoringSessionGroupRequest $request, ScoringSessionGroup $group): JsonResponse
    {
        $group = $this->groups->updateGroup($group, $request->validated());

        return ApiResponse::success(
            new ScoringSessionGroupResource($group->load('host')),
            'Group updated',
        );
    }

    /**
     * Batch quick-add of guests in one call — idempotent (task 2.4).
     */
    public function addParticipants(AddGroupParticipantsRequest $request, ScoringSessionGroup $group): JsonResponse
    {
        $participants = $this->groups->addParticipants(
            $group,
            $request->user(),
            $request->validated()['participants'],
        );

        return ApiResponse::success(
            GroupParticipantResource::collection($participants),
            'Participants added',
            201,
        );
    }

    /**
     * Remove a participant: host removes anyone, a participant leaves their own
     * row (task 2.5).
     */
    public function removeParticipant(Request $request, ScoringSessionGroup $group, ScoringSession $session): JsonResponse
    {
        abort_unless($session->scoring_session_group_id === $group->id, 404, 'Resource not found.');

        $user = $request->user();
        $isOwnRow = $session->user_id !== null && $session->user_id === $user->id;
        abort_unless($user->can('manage', $group) || $isOwnRow, 404, 'Resource not found.');

        $this->groups->removeParticipant($session);

        return ApiResponse::success(null, 'Participant removed');
    }

    /**
     * Idempotent score input for one participant row (task 3.1/3.2). Authorized
     * (host or row-owner) in the FormRequest. Offline-first: the client may
     * re-send the same ends and the result is identical (last-write-wins).
     */
    public function scoreParticipant(
        ScoreGroupParticipantRequest $request,
        ScoringSessionGroup $group,
        ScoringSession $session,
    ): JsonResponse {
        $participant = $this->groups->persistParticipantScore(
            $group,
            $session,
            $request->user(),
            $request->validated(),
        );

        return ApiResponse::success(new GroupParticipantResource($participant), 'Score saved');
    }

    /**
     * Idempotent batch sync of participant scores for offline reconciliation
     * (task 3.3). Forgives flaky connectivity: re-sends never duplicate.
     */
    public function sync(SyncGroupParticipantsRequest $request, ScoringSessionGroup $group): JsonResponse
    {
        $participants = $this->groups->syncParticipantScores(
            $group,
            $request->user(),
            $request->validated()['sessions'],
        );

        return ApiResponse::success(
            GroupParticipantResource::collection($participants),
            'Participants synced',
        );
    }

    /**
     * Fair aggregate leaderboard (tasks 3.4–3.6). Host or participant only.
     * Sending the last-seen `?version=` short-circuits to an empty payload when
     * nothing changed — the cheap polling cursor for Phase 1.
     */
    public function leaderboard(Request $request, ScoringSessionGroup $group): JsonResponse
    {
        abort_unless($request->user()->can('view', $group), 404, 'Resource not found.');

        $version = $this->groups->leaderboardVersion($group);
        if ($request->query('version') !== null && $request->query('version') === $version) {
            return ApiResponse::success(
                ['entries' => []],
                'Leaderboard unchanged',
                200,
                ['version' => $version, 'unchanged' => true],
            );
        }

        $board = $this->groups->leaderboard($group);

        return ApiResponse::success(['entries' => $board['entries']], 'OK', 200, $board['meta']);
    }
}
