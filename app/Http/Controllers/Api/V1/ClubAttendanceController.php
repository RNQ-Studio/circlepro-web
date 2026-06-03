<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\ClubAttendance;
use App\Models\ClubSchedule;
use App\Models\Organization;
use App\Services\ClubService;
use App\Support\ApiResponse;
use App\Support\Enums\AttendanceStatus;
use App\Support\Enums\OrganizationType;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ClubAttendanceController extends Controller
{
    public function __construct(private readonly ClubService $clubs) {}

    public function index(Request $request, Organization $club, ClubSchedule $schedule): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);
        abort_unless($schedule->organization_id === $club->id, 404, 'Schedule not found in this club.');

        $members = $club->members()
            ->where('status', 'active')
            ->with(['user.profile'])
            ->get();

        $attendances = ClubAttendance::query()
            ->where('club_schedule_id', $schedule->id)
            ->get()
            ->keyBy('user_id');

        $data = $members->map(function ($member) use ($attendances) {
            $attendance = $attendances->get($member->user_id);
            return [
                'id' => $attendance?->id,
                'user' => [
                    'id' => $member->user?->id,
                    'full_name' => $member->user?->full_name ?? $member->user?->name,
                    'username' => $member->user?->username,
                    'avatar_url' => $member->user?->profile?->avatar_url,
                ],
                'status' => $attendance?->status?->value,
                'remark' => $attendance?->remark,
            ];
        });

        return ApiResponse::success($data);
    }

    public function store(Request $request, Organization $club, ClubSchedule $schedule): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureAdmin($request->user(), $club);
        abort_unless($schedule->organization_id === $club->id, 404, 'Schedule not found in this club.');

        $validated = $request->validate([
            'attendances' => 'required|array',
            'attendances.*.user_id' => 'required|exists:users,id',
            'attendances.*.status' => ['required', Rule::enum(AttendanceStatus::class)],
            'attendances.*.remark' => 'nullable|string|max:255',
        ]);

        $memberUserIds = $club->members()
            ->where('status', 'active')
            ->pluck('user_id')
            ->toArray();

        foreach ($validated['attendances'] as $item) {
            if (!in_array($item['user_id'], $memberUserIds, true)) {
                continue;
            }

            ClubAttendance::query()->updateOrCreate(
                [
                    'club_schedule_id' => $schedule->id,
                    'user_id' => $item['user_id'],
                ],
                [
                    'status' => $item['status'],
                    'remark' => $item['remark'] ?? null,
                    'marked_by' => $request->user()->id,
                ]
            );
        }

        return ApiResponse::success(null, 'Attendance recorded successfully.');
    }

    public function myAttendance(Request $request, Organization $club): JsonResponse
    {
        $this->ensureClub($club);
        $this->ensureMember($request->user(), $club);

        $attendances = ClubAttendance::query()
            ->where('user_id', $request->user()->id)
            ->whereHas('schedule', function ($query) use ($club) {
                $query->where('organization_id', $club->id);
            })
            ->with('schedule')
            ->get();

        $data = $attendances->map(fn ($att) => [
            'id' => $att->id,
            'status' => $att->status->value,
            'remark' => $att->remark,
            'marked_at' => $att->updated_at?->toIso8601String(),
            'schedule' => [
                'id' => $att->schedule->id,
                'title' => $att->schedule->title,
                'description' => $att->schedule->description,
                'location' => $att->schedule->location,
                'start_time' => $att->schedule->start_time->toIso8601String(),
                'end_time' => $att->schedule->end_time->toIso8601String(),
            ]
        ]);

        return ApiResponse::success($data);
    }

    private function ensureClub(Organization $club): void
    {
        abort_unless($club->type === OrganizationType::Club, 404, 'Club not found.');
    }

    private function ensureAdmin(\App\Models\User $user, Organization $club): void
    {
        abort_unless($this->clubs->isAdmin($user, $club), 403, 'Admin role required.');
    }

    private function ensureMember(\App\Models\User $user, Organization $club): void
    {
        abort_unless($this->clubs->membershipOf($user, $club) !== null, 403, 'Club membership required.');
    }
}
