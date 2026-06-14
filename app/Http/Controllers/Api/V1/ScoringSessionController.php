<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreScoringSessionRequest;
use App\Http\Requests\Api\V1\SyncScoringSessionsRequest;
use App\Http\Requests\Api\V1\UpdateScoringSessionRequest;
use App\Http\Resources\Api\V1\ScoringSessionResource;
use App\Models\ScoringSession;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Scoring\ScoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Scoring sessions API (Module 1 — TRACK). Offline-first: clients generate
 * the ULID + client_uuid and the server upserts idempotently.
 */
class ScoringSessionController extends Controller
{
    public function __construct(private readonly ScoringService $scoring) {}

    /**
     * History list with filters & pagination (task 1.7). Excludes ends for a
     * lightweight payload.
     */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);
        $userId = $request->user()->id;

        $sessions = QueryBuilder::for(ScoringSession::query()->where('user_id', $userId))
            ->allowedFilters(
                AllowedFilter::exact('bow_class'),
                AllowedFilter::exact('distance_category'),
                AllowedFilter::exact('status'),
                AllowedFilter::scope('started_after'),
                AllowedFilter::scope('started_before'),
            )
            ->allowedSorts('started_at', 'total_score', 'created_at')
            ->defaultSort('-started_at')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ScoringSessionResource::collection($sessions));
    }

    public function store(StoreScoringSessionRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->validated();

        // Resolve session to see if it is new
        $query = ScoringSession::where('user_id', $user->id);
        $exists = false;
        if (! empty($data['client_uuid'])) {
            $exists = (clone $query)->where('client_uuid', $data['client_uuid'])->exists();
        }
        if (! $exists && ! empty($data['id'])) {
            $exists = (clone $query)->whereKey($data['id'])->exists();
        }

        if (! $exists) {
            $this->checkScoringLimit($user, 1);
        }

        $session = $this->scoring->persistSession($user, $data);

        return ApiResponse::success(new ScoringSessionResource($session), 'Scoring session saved', 201);
    }

    public function show(Request $request, ScoringSession $scoringSession): JsonResponse
    {
        $this->authorizeOwner($request, $scoringSession);

        $scoringSession->load('ends.arrows');

        return ApiResponse::success(new ScoringSessionResource($scoringSession));
    }

    public function update(UpdateScoringSessionRequest $request, ScoringSession $scoringSession): JsonResponse
    {
        $this->authorizeOwner($request, $scoringSession);

        $data = $request->validated();
        $data['id'] = $scoringSession->id;

        $session = $this->scoring->persistSession($request->user(), $data);

        return ApiResponse::success(new ScoringSessionResource($session), 'Scoring session updated');
    }

    public function destroy(Request $request, ScoringSession $scoringSession): JsonResponse
    {
        $this->authorizeOwner($request, $scoringSession);

        $scoringSession->delete();

        return ApiResponse::success(null, 'Scoring session deleted');
    }

    /**
     * Per-session analytics (task 1.3).
     */
    public function summary(Request $request, ScoringSession $scoringSession): JsonResponse
    {
        $this->authorizeOwner($request, $scoringSession);

        return ApiResponse::success($this->scoring->summary($scoringSession));
    }

    /**
     * Idempotent batch sync of offline sessions (task 1.13a).
     */
    public function sync(SyncScoringSessionsRequest $request): JsonResponse
    {
        $user = $request->user();
        $sessionsData = $request->validated()['sessions'];

        $this->checkScoringLimitForSync($user, $sessionsData);

        $sessions = $this->scoring->syncSessions($user, $sessionsData);

        return ApiResponse::success(
            ScoringSessionResource::collection($sessions),
            'Sessions synced',
        );
    }

    private function checkScoringLimit(User $user, int $additionalCount = 1): void
    {
        $sub = Subscription::where('user_id', $user->id)
            ->where('subscriber_type', 'user')
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        if ($sub && $sub->isActive()) {
            return; // Premium users have unlimited scoring
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);

        $existingCount = ScoringSession::where('user_id', $user->id)
            ->where('started_at', '>=', $startOfWeek)
            ->count();

        if ($existingCount + $additionalCount > 3) {
            abort(402, 'Scoring session limit reached for Free plan. Upgrade to Pro/Elite to record unlimited sessions.');
        }
    }

    private function checkScoringLimitForSync(User $user, array $sessions): void
    {
        $sub = Subscription::where('user_id', $user->id)
            ->where('subscriber_type', 'user')
            ->whereIn('status', ['active', 'trialing'])
            ->first();

        if ($sub && $sub->isActive()) {
            return; // Premium users have unlimited scoring
        }

        $newCount = 0;
        foreach ($sessions as $sessionData) {
            $query = ScoringSession::where('user_id', $user->id);
            $exists = false;

            if (! empty($sessionData['client_uuid'])) {
                $exists = (clone $query)->where('client_uuid', $sessionData['client_uuid'])->exists();
            }

            if (! $exists && ! empty($sessionData['id'])) {
                $exists = (clone $query)->whereKey($sessionData['id'])->exists();
            }

            if (! $exists) {
                $newCount++;
            }
        }

        if ($newCount === 0) {
            return;
        }

        $startOfWeek = Carbon::now()->startOfWeek(Carbon::MONDAY);
        $existingCount = ScoringSession::where('user_id', $user->id)
            ->where('started_at', '>=', $startOfWeek)
            ->count();

        if ($existingCount + $newCount > 3) {
            abort(402, 'Scoring session limit reached for Free plan. Upgrade to Pro/Elite to sync new sessions.');
        }
    }

    /**
     * Progress dashboard aggregates (task 1.9).
     */
    public function dashboard(Request $request): JsonResponse
    {
        $filters = $request->only(['bow_class', 'distance_category']);

        return ApiResponse::success($this->scoring->dashboard($request->user(), $filters));
    }

    private function authorizeOwner(Request $request, ScoringSession $session): void
    {
        abort_unless($session->user_id === $request->user()->id, 404, 'Resource not found.');
    }
}
