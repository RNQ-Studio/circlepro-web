<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ClubScheduleResource;
use App\Models\ClubSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Services\ClubService;
use App\Support\ApiResponse;
use App\Support\Enums\OrganizationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClubScheduleController extends Controller
{
    public function __construct(private readonly ClubService $clubs) {}

    public function index(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureMember($request->user(), $club);

        $schedules = ClubSchedule::query()
            ->where('organization_id', $club->id)
            ->with(['attendances' => function ($query) use ($request) {
                $query->where('user_id', $request->user()->id);
            }])
            ->orderBy('start_time', 'asc')
            ->get();

        return ApiResponse::success(ClubScheduleResource::collection($schedules));
    }

    public function store(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);

        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:200',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $schedule = ClubSchedule::query()->create([
            'organization_id' => $club->id,
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
            'created_by' => $request->user()->id,
        ]);

        return ApiResponse::success(new ClubScheduleResource($schedule), 'Schedule created.', 201);
    }

    public function show(Request $request, Organization $club, ClubSchedule $schedule): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureMember($request->user(), $club);
        abort_unless($schedule->organization_id === $club->id, 404, 'Schedule not found in this club.');

        $schedule->load(['attendances' => function ($query) use ($request) {
            $query->where('user_id', $request->user()->id);
        }]);

        return ApiResponse::success(new ClubScheduleResource($schedule));
    }

    public function update(Request $request, Organization $club, ClubSchedule $schedule): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);
        abort_unless($schedule->organization_id === $club->id, 404, 'Schedule not found in this club.');

        $validated = $request->validate([
            'title' => 'required|string|max:160',
            'description' => 'nullable|string',
            'location' => 'nullable|string|max:200',
            'start_time' => 'required|date',
            'end_time' => 'required|date|after:start_time',
        ]);

        $schedule->update([
            'title' => $validated['title'],
            'description' => $validated['description'] ?? null,
            'location' => $validated['location'] ?? null,
            'start_time' => $validated['start_time'],
            'end_time' => $validated['end_time'],
        ]);

        return ApiResponse::success(new ClubScheduleResource($schedule), 'Schedule updated.');
    }

    public function destroy(Request $request, Organization $club, ClubSchedule $schedule): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);
        abort_unless($schedule->organization_id === $club->id, 404, 'Schedule not found in this club.');

        $schedule->delete();

        return ApiResponse::success(null, 'Schedule deleted.');
    }

    private function ensureClub(Organization $club): void
    {
        abort_unless($club->type === OrganizationType::Club, 404, 'Club not found.');
    }

    private function ensureAdmin(User $user, Organization $club): void
    {
        abort_unless($this->clubs->isAdmin($user, $club), 403, 'Admin role required.');
    }

    private function ensureMember(User $user, Organization $club): void
    {
        abort_unless($this->clubs->membershipOf($user, $club) !== null, 403, 'Club membership required.');
    }
}
