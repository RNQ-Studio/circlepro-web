<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\V1\StoreClubRequest;
use App\Http\Requests\Api\V1\UpdateClubRequest;
use App\Http\Resources\Api\V1\ClubMemberResource;
use App\Http\Resources\Api\V1\ClubResource;
use App\Models\Organization;
use App\Models\ScoringSession;
use App\Models\User;
use App\Services\ClubService;
use App\Support\ApiResponse;
use App\Support\Enums\MemberRole;
use App\Support\Enums\OrganizationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Spatie\QueryBuilder\AllowedFilter;
use Spatie\QueryBuilder\QueryBuilder;

/**
 * Clubs (organizations type=club) + membership (Module 0, task 2.7).
 */
class ClubController extends Controller
{
    public function __construct(private readonly ClubService $clubs) {}

    /** Club directory with search/filter (task 2.8). */
    public function index(Request $request): JsonResponse
    {
        $perPage = min(max((int) $request->integer('per_page', 20), 1), 100);

        $clubs = QueryBuilder::for(
            Organization::query()
                ->where('type', OrganizationType::Club->value)
                ->withCount(['members as member_count' => fn ($q) => $q->where('status', 'active')])
        )
            ->allowedFilters(
                AllowedFilter::exact('province'),
                AllowedFilter::exact('city'),
                AllowedFilter::scope('search'),
            )
            ->allowedSorts('name', 'created_at', 'member_count')
            ->defaultSort('name')
            ->paginate($perPage)
            ->appends($request->query());

        return ApiResponse::success(ClubResource::collection($clubs));
    }

    public function store(StoreClubRequest $request): JsonResponse
    {
        $club = $this->clubs->create($request->user(), $request->validated());

        $resource = (new ClubResource($club));
        $resource->myRole = MemberRole::Owner->value;
        $resource->isMember = true;

        return ApiResponse::success($resource, 'Club created', 201);
    }

    public function show(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);

        return ApiResponse::success($this->present($request->user(), $club));
    }

    public function update(UpdateClubRequest $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);

        $club->update($request->validated());

        return ApiResponse::success($this->present($request->user(), $club->refresh()), 'Club updated');
    }

    /** Clubs the current user belongs to. */
    public function mine(Request $request): JsonResponse
    {
        $clubs = Organization::query()
            ->where('type', OrganizationType::Club->value)
            ->whereIn('id', $request->user()->organizationMemberships()->pluck('organization_id'))
            ->withCount(['members as member_count' => fn ($q) => $q->where('status', 'active')])
            ->orderBy('name')
            ->get();

        return ApiResponse::success(ClubResource::collection($clubs));
    }

    public function join(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->clubs->join($request->user(), $club);

        return ApiResponse::success($this->present($request->user(), $club->refresh()), 'Joined club');
    }

    public function leave(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->clubs->leave($request->user(), $club);

        return ApiResponse::success(null, 'Left club');
    }

    public function members(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);

        $members = $club->members()
            ->with('user.profile')
            ->orderByRaw("array_position(ARRAY['owner','admin','coach','scorer','member']::text[], (role)::text)")
            ->paginate(min(max((int) $request->integer('per_page', 30), 1), 100))
            ->appends($request->query());

        return ApiResponse::success(ClubMemberResource::collection($members));
    }

    public function removeMember(Request $request, Organization $club, User $user): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);

        $membership = $this->clubs->membershipOf($user, $club);
        abort_if($membership === null, 404, 'Member not found.');
        abort_if($membership->role === MemberRole::Owner, 403, 'Cannot remove the owner.');

        $membership->delete();

        return ApiResponse::success(null, 'Member removed');
    }

    public function updateRole(Request $request, Organization $club, User $user): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);

        $validated = $request->validate(['role' => ['required', Rule::enum(MemberRole::class)]]);

        $membership = $this->clubs->membershipOf($user, $club);
        abort_if($membership === null, 404, 'Member not found.');
        abort_if($membership->role === MemberRole::Owner, 403, 'Cannot change the owner role.');

        $membership->update(['role' => $validated['role']]);

        return ApiResponse::success(new ClubMemberResource($membership->load('user.profile')), 'Role updated');
    }

    /** Recent club-tagged scoring sessions (task 2.10a). */
    public function activity(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);

        $sessions = ScoringSession::query()
            ->where('organization_id', $club->id)
            ->with('user.profile')
            ->orderByDesc('started_at')
            ->limit(30)
            ->get()
            ->map(fn (ScoringSession $s): array => [
                'session_id' => $s->id,
                'user' => [
                    'id' => $s->user?->id,
                    'full_name' => $s->user->full_name ?? $s->user->name,
                    'avatar_url' => $s->user?->profile?->avatar_url,
                ],
                'bow_class' => $s->bow_class->value,
                'distance_category' => $s->distance_category->value,
                'total_score' => $s->total_score,
                'is_personal_best' => $s->is_personal_best,
                'started_at' => $s->started_at->toIso8601String(),
            ]);

        return ApiResponse::success($sessions);
    }

    private function ensureClub(Organization $club): void
    {
        abort_unless($club->type === OrganizationType::Club, 404, 'Club not found.');
    }

    private function ensureAdmin(User $user, Organization $club): void
    {
        abort_unless($this->clubs->isAdmin($user, $club), 403, 'Admin role required.');
    }

    private function present(User $user, Organization $club): ClubResource
    {
        $membership = $this->clubs->membershipOf($user, $club);

        $resource = new ClubResource($club);
        $resource->myRole = $membership?->role->value;
        $resource->isMember = $membership !== null;

        return $resource;
    }
}
