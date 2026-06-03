<?php

namespace Tests\Feature\Api;

use App\Models\ClubSchedule;
use App\Models\Organization;
use App\Models\User;
use App\Support\Enums\AttendanceStatus;
use App\Support\Enums\MemberRole;
use App\Support\Enums\OrganizationType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClubScheduleAndAttendanceTest extends TestCase
{
    use RefreshDatabase;

    private Organization $club;
    private User $owner;
    private User $member;
    private User $nonMember;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = User::factory()->create();
        $this->member = User::factory()->create();
        $this->nonMember = User::factory()->create();

        // Create club via direct Eloquent to isolate tests
        $this->club = Organization::factory()->create([
            'type' => OrganizationType::Club->value,
            'name' => 'Test Archery Club',
        ]);

        // Setup memberships
        $this->club->members()->create([
            'user_id' => $this->owner->id,
            'role' => MemberRole::Owner->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);

        $this->club->members()->create([
            'user_id' => $this->member->id,
            'role' => MemberRole::Member->value,
            'status' => 'active',
            'joined_at' => now(),
        ]);
    }

    public function test_admin_can_create_update_delete_schedules(): void
    {
        Passport::actingAs($this->owner);

        // 1. Create schedule
        $response = $this->postJson("/api/v1/clubs/{$this->club->id}/schedules", [
            'title' => 'Sabtu Pagi Rutin',
            'description' => 'Latihan bersama seluruh anggota.',
            'location' => 'Lapangan Utama',
            'start_time' => now()->addDay()->setHour(8)->setMinute(0)->toIso8601String(),
            'end_time' => now()->addDay()->setHour(10)->setMinute(0)->toIso8601String(),
        ]);

        $response->assertCreated();
        $response->assertJsonPath('data.title', 'Sabtu Pagi Rutin');
        $scheduleId = $response->json('data.id');

        // 2. Update schedule
        $this->putJson("/api/v1/clubs/{$this->club->id}/schedules/{$scheduleId}", [
            'title' => 'Sabtu Pagi Rutin Edit',
            'start_time' => now()->addDay()->setHour(9)->setMinute(0)->toIso8601String(),
            'end_time' => now()->addDay()->setHour(11)->setMinute(0)->toIso8601String(),
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Sabtu Pagi Rutin Edit');

        // 3. Delete schedule
        $this->deleteJson("/api/v1/clubs/{$this->club->id}/schedules/{$scheduleId}")
            ->assertOk();

        $this->assertDatabaseMissing('club_schedules', ['id' => $scheduleId]);
    }

    public function test_normal_member_cannot_modify_schedules(): void
    {
        Passport::actingAs($this->member);

        // Attempt create
        $this->postJson("/api/v1/clubs/{$this->club->id}/schedules", [
            'title' => 'Latihan Member',
            'start_time' => now()->addDay()->toIso8601String(),
            'end_time' => now()->addDay()->addHours(2)->toIso8601String(),
        ])->assertForbidden();

        // Admin creates one first
        $schedule = ClubSchedule::query()->create([
            'organization_id' => $this->club->id,
            'title' => 'Jadwal Admin',
            'start_time' => now()->addDay(),
            'end_time' => now()->addDay()->addHours(2),
            'created_by' => $this->owner->id,
        ]);

        // Attempt update
        $this->putJson("/api/v1/clubs/{$this->club->id}/schedules/{$schedule->id}", [
            'title' => 'Jadwal Admin Edit',
            'start_time' => now()->addDay()->toIso8601String(),
            'end_time' => now()->addDay()->addHours(2)->toIso8601String(),
        ])->assertForbidden();

        // Attempt delete
        $this->deleteJson("/api/v1/clubs/{$this->club->id}/schedules/{$schedule->id}")
            ->assertForbidden();
    }

    public function test_members_can_list_schedules_and_see_own_attendance(): void
    {
        // Admin creates schedule
        $schedule = ClubSchedule::query()->create([
            'organization_id' => $this->club->id,
            'title' => 'Latihan Akhir Pekan',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'created_by' => $this->owner->id,
        ]);

        // Member is marked as present
        $schedule->attendances()->create([
            'user_id' => $this->member->id,
            'status' => AttendanceStatus::Present->value,
            'remark' => 'Datang tepat waktu',
            'marked_by' => $this->owner->id,
        ]);

        // 1. Member lists schedules
        Passport::actingAs($this->member);
        $response = $this->getJson("/api/v1/clubs/{$this->club->id}/schedules")
            ->assertOk()
            ->assertJsonCount(1, 'data');

        $response->assertJsonPath('data.0.my_attendance.status', 'present');
        $response->assertJsonPath('data.0.my_attendance.remark', 'Datang tepat waktu');

        // 2. Non-member lists schedules
        Passport::actingAs($this->nonMember);
        $this->getJson("/api/v1/clubs/{$this->club->id}/schedules")
            ->assertForbidden();
    }

    public function test_admin_can_retrieve_and_bulk_save_attendance(): void
    {
        $schedule = ClubSchedule::query()->create([
            'organization_id' => $this->club->id,
            'title' => 'Sesi Absensi',
            'start_time' => now()->subDay(),
            'end_time' => now()->subDay()->addHours(2),
            'created_by' => $this->owner->id,
        ]);

        // 1. Get attendance sheet (Admin only)
        Passport::actingAs($this->owner);
        $this->getJson("/api/v1/clubs/{$this->club->id}/schedules/{$schedule->id}/attendance")
            ->assertOk()
            ->assertJsonCount(2, 'data'); // owner and member

        // 2. Bulk record attendance
        $this->postJson("/api/v1/clubs/{$this->club->id}/schedules/{$schedule->id}/attendance", [
            'attendances' => [
                [
                    'user_id' => $this->member->id,
                    'status' => 'present',
                    'remark' => 'Hadir dengan busur baru',
                ],
                [
                    'user_id' => $this->owner->id,
                    'status' => 'excused',
                    'remark' => 'Mengawasi latihan saja',
                ]
            ]
        ])->assertOk();

        $this->assertDatabaseHas('club_attendances', [
            'club_schedule_id' => $schedule->id,
            'user_id' => $this->member->id,
            'status' => 'present',
            'remark' => 'Hadir dengan busur baru',
        ]);

        // 3. Non-admin attempts to save attendance
        Passport::actingAs($this->member);
        $this->postJson("/api/v1/clubs/{$this->club->id}/schedules/{$schedule->id}/attendance", [
            'attendances' => []
        ])->assertForbidden();
    }

    public function test_member_can_retrieve_my_attendance_history(): void
    {
        $schedule1 = ClubSchedule::query()->create([
            'organization_id' => $this->club->id,
            'title' => 'Latihan 1',
            'start_time' => now()->subDays(2),
            'end_time' => now()->subDays(2)->addHours(2),
            'created_by' => $this->owner->id,
        ]);

        $schedule2 = ClubSchedule::query()->create([
            'organization_id' => $this->club->id,
            'title' => 'Latihan 2',
            'start_time' => now()->subDays(1),
            'end_time' => now()->subDays(1)->addHours(2),
            'created_by' => $this->owner->id,
        ]);

        // Setup attendance records
        $schedule1->attendances()->create([
            'user_id' => $this->member->id,
            'status' => AttendanceStatus::Present->value,
            'remark' => 'Latihan pagi',
            'marked_by' => $this->owner->id,
        ]);

        $schedule2->attendances()->create([
            'user_id' => $this->member->id,
            'status' => AttendanceStatus::Absent->value,
            'remark' => 'Tanpa keterangan',
            'marked_by' => $this->owner->id,
        ]);

        Passport::actingAs($this->member);
        $this->getJson("/api/v1/clubs/{$this->club->id}/my-attendance")
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.status', 'present')
            ->assertJsonPath('data.1.status', 'absent');
    }
}
