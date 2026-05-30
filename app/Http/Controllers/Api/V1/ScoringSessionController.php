<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreScoringSessionRequest;
use App\Http\Requests\Api\V1\SyncScoringSessionsRequest;
use App\Http\Requests\Api\V1\UpdateScoringSessionRequest;
use App\Http\Resources\Api\V1\ScoringSessionResource;
use App\Models\ScoringSession;
use App\Services\Scoring\ScoringService;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
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
        $session = $this->scoring->persistSession($request->user(), $request->validated());

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
        $sessions = $this->scoring->syncSessions($request->user(), $request->validated()['sessions']);

        return ApiResponse::success(
            ScoringSessionResource::collection($sessions),
            'Sessions synced',
        );
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
