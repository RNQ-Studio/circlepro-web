<?php

namespace Tests\Feature\Api;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class ClubTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_create_club_and_appears_in_directory(): void
    {
        Passport::actingAs(User::factory()->create());

        $id = $this->postJson('/api/v1/clubs', [
            'name' => 'Sasana Panahan Bandung',
            'city' => 'Bandung',
            'province' => 'Jawa Barat',
        ])
            ->assertCreated()
            ->assertJsonPath('data.my_role', 'owner')
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.member_count', 1)
            ->json('data.id');

        $this->getJson('/api/v1/clubs?filter[search]=Bandung')
            ->assertOk()
            ->assertJsonPath('data.0.id', $id);
    }

    public function test_member_join_leave_and_listing(): void
    {
        $owner = User::factory()->create();
        Passport::actingAs($owner);
        $clubId = $this->postJson('/api/v1/clubs', ['name' => 'Klub Test'])->json('data.id');

        $member = User::factory()->create();
        Passport::actingAs($member);

        $this->postJson("/api/v1/clubs/{$clubId}/join")
            ->assertOk()
            ->assertJsonPath('data.is_member', true)
            ->assertJsonPath('data.member_count', 2);

        $this->getJson('/api/v1/clubs/mine')
            ->assertOk()
            ->assertJsonPath('data.0.id', $clubId);

        $this->getJson("/api/v1/clubs/{$clubId}/members")
            ->assertOk()
            ->assertJsonCount(2, 'data');

        $this->postJson("/api/v1/clubs/{$clubId}/leave")->assertOk();
        $this->getJson("/api/v1/clubs/{$clubId}")
            ->assertOk()
            ->assertJsonPath('data.is_member', false);
    }

    public function test_only_admin_can_update_club_and_manage_members(): void
    {
        $owner = User::factory()->create();
        Passport::actingAs($owner);
        $clubId = $this->postJson('/api/v1/clubs', ['name' => 'Klub Admin'])->json('data.id');

        $member = User::factory()->create();
        Passport::actingAs($member);
        $this->postJson("/api/v1/clubs/{$clubId}/join")->assertOk();

        // Member cannot update club.
        $this->putJson("/api/v1/clubs/{$clubId}", ['name' => 'Hacked'])
            ->assertForbidden();

        // Owner can update + change member role + remove member.
        Passport::actingAs($owner);
        $this->putJson("/api/v1/clubs/{$clubId}", ['name' => 'Klub Resmi'])
            ->assertOk()
            ->assertJsonPath('data.name', 'Klub Resmi');

        $this->putJson("/api/v1/clubs/{$clubId}/members/{$member->id}/role", ['role' => 'coach'])
            ->assertOk()
            ->assertJsonPath('data.role', 'coach');

        $this->deleteJson("/api/v1/clubs/{$clubId}/members/{$member->id}")->assertOk();

        $this->assertDatabaseMissing('organization_members', [
            'organization_id' => $clubId,
            'user_id' => $member->id,
        ]);
    }
}
