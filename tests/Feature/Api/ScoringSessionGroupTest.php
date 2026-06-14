<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ParticipationStatus;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 02 — Lifecycle Grup & Quick-Add Tamu (Phase 0, backend).
 * Covers create + anti-ambiguous join_code, list/detail, lookup-by-code,
 * batch quick-add of guests (idempotent), removal, finish/abandon and the
 * host-only authorization (404 privacy).
 */
class ScoringSessionGroupTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function groupPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'Latihan Sore Klub',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 6,
            'arrows_per_end' => 6,
        ], $overrides);
    }

    public function test_guest_cannot_access_groups(): void
    {
        $this->getJson('/api/v1/scoring/groups')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_host_creates_a_group_with_anti_ambiguous_join_code(): void
    {
        Passport::actingAs($host = User::factory()->create());

        $response = $this->postJson('/api/v1/scoring/groups', $this->groupPayload())
            ->assertCreated()
            ->assertJsonPath('data.status', 'in_progress')
            ->assertJsonPath('data.distance_m', 50)
            ->assertJsonPath('data.num_ends', 6)
            ->assertJsonPath('data.host.id', $host->id)
            ->assertJsonPath('data.participant_count', 0);

        $code = $response->json('data.join_code');
        // Anti-ambiguous: no O/0/I/1, fixed length, uppercase safe alphabet.
        $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code);
    }

    public function test_join_codes_are_unique_and_safe_across_many_groups(): void
    {
        Passport::actingAs(User::factory()->create());

        $codes = [];
        for ($i = 0; $i < 8; $i++) {
            $codes[] = $this->postJson('/api/v1/scoring/groups', $this->groupPayload())
                ->assertCreated()
                ->json('data.join_code');
        }

        $this->assertCount(8, array_unique($codes));
        foreach ($codes as $code) {
            $this->assertMatchesRegularExpression('/^[ABCDEFGHJKLMNPQRSTUVWXYZ23456789]{6}$/', $code);
        }
    }

    public function test_host_can_join_as_a_shooter(): void
    {
        Passport::actingAs($host = User::factory()->create());

        $id = $this->postJson('/api/v1/scoring/groups', $this->groupPayload([
            'host_participates' => true,
            'host_bow_class' => 'recurve',
        ]))
            ->assertCreated()
            ->assertJsonPath('data.participant_count', 1)
            ->json('data.id');

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $id,
            'user_id' => $host->id,
            'participation_status' => ParticipationStatus::Self->value,
            'bow_class' => 'recurve',
        ]);
    }

    public function test_batch_quick_add_inserts_many_guests_with_optional_metadata(): void
    {
        Passport::actingAs($host = User::factory()->create());

        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload())
            ->json('data.id');

        // Name-only must work (K8): metadata must not block adding a person.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [
                ['name' => 'Pak Budi'],
                ['name' => 'Andi', 'bow_class' => 'compound'],
                ['name' => 'Rina', 'target_butt' => 3, 'target_letter' => 'A'],
            ],
        ])
            ->assertCreated()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.display_name', 'Pak Budi')
            ->assertJsonPath('data.0.is_guest', true)
            ->assertJsonPath('data.0.bow_class', null);

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $groupId,
            'guest_name' => 'Pak Budi',
            'user_id' => null,
            'participation_status' => ParticipationStatus::HostAdded->value,
            'added_by_user_id' => $host->id,
            // Inherits the group round format (K8: metadata optional).
            'distance_m' => 50,
            'num_ends' => 6,
        ]);

        $this->assertSame(3, ScoringSessionGroup::find($groupId)->participants()->count());
    }

    public function test_quick_add_is_idempotent_by_client_uuid(): void
    {
        Passport::actingAs(User::factory()->create());

        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload())
            ->json('data.id');

        $uuid = (string) Str::uuid();
        $body = ['participants' => [['name' => 'Budi', 'client_uuid' => $uuid]]];

        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", $body)->assertCreated();
        // Double-tap: same client_uuid must not duplicate the roster row.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", $body)->assertCreated();

        $this->assertSame(1, ScoringSessionGroup::find($groupId)->participants()->count());
    }

    public function test_lookup_by_code_shows_round_format_without_full_roster(): void
    {
        Passport::actingAs($host = User::factory()->create());
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create([
            'join_code' => 'ABC234',
            'distance_m' => 30,
            'num_ends' => 10,
        ]);
        ScoringSession::factory()->guest()->count(2)->create(['scoring_session_group_id' => $group->id]);

        // A different user previews by code (case-insensitive input).
        Passport::actingAs(User::factory()->create());
        $this->getJson('/api/v1/scoring/groups/lookup?code=abc234')
            ->assertOk()
            ->assertJsonPath('data.distance_m', 30)
            ->assertJsonPath('data.num_ends', 10)
            ->assertJsonPath('data.participant_count', 2)
            ->assertJsonMissingPath('data.participants');

        $this->getJson('/api/v1/scoring/groups/lookup?code=NOPE99')->assertNotFound();
    }

    public function test_index_returns_groups_i_host_or_participate_in(): void
    {
        $me = User::factory()->create();
        $other = User::factory()->create();

        $hosted = ScoringSessionGroup::factory()->for($me, 'host')->create();
        $joined = ScoringSessionGroup::factory()->for($other, 'host')->create();
        ScoringSession::factory()->for($me)->create(['scoring_session_group_id' => $joined->id]);
        $foreign = ScoringSessionGroup::factory()->for($other, 'host')->create();

        Passport::actingAs($me);
        $ids = collect($this->getJson('/api/v1/scoring/groups')->assertOk()->json('data'))
            ->pluck('id');

        $this->assertTrue($ids->contains($hosted->id));
        $this->assertTrue($ids->contains($joined->id));
        $this->assertFalse($ids->contains($foreign->id));
    }

    public function test_host_can_finish_a_group(): void
    {
        Passport::actingAs(User::factory()->create());
        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload())->json('data.id');

        $this->patchJson("/api/v1/scoring/groups/{$groupId}", ['status' => 'completed'])
            ->assertOk()
            ->assertJsonPath('data.status', 'completed');

        $this->assertDatabaseHas('scoring_session_groups', [
            'id' => $groupId,
            'status' => ScoringSessionStatus::Completed->value,
        ]);
        $this->assertNotNull(ScoringSessionGroup::find($groupId)->completed_at);
    }

    public function test_format_edit_allowed_before_scores_blocked_after(): void
    {
        Passport::actingAs($host = User::factory()->create());
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create(['num_ends' => 6]);

        // No scores yet → format edit allowed.
        $this->patchJson("/api/v1/scoring/groups/{$group->id}", ['num_ends' => 12])
            ->assertOk()
            ->assertJsonPath('data.num_ends', 12);

        // A participant now has a score → format becomes locked.
        ScoringSession::factory()->guest()->create([
            'scoring_session_group_id' => $group->id,
            'arrows_shot' => 6,
            'total_score' => 54,
        ]);

        $this->patchJson("/api/v1/scoring/groups/{$group->id}", ['distance_m' => 18])
            ->assertStatus(422);

        // Finishing is still allowed even after scores exist.
        $this->patchJson("/api/v1/scoring/groups/{$group->id}", ['status' => 'completed'])->assertOk();
    }

    public function test_host_removes_anyone_and_participant_leaves_own_row(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();

        $guest = ScoringSession::factory()->guest()->create(['scoring_session_group_id' => $group->id]);
        $memberRow = ScoringSession::factory()->for($member)->create(['scoring_session_group_id' => $group->id]);

        // Host removes the guest.
        Passport::actingAs($host);
        $this->deleteJson("/api/v1/scoring/groups/{$group->id}/participants/{$guest->id}")->assertOk();
        $this->assertSoftDeleted('scoring_sessions', ['id' => $guest->id]);

        // The member removes their own row.
        Passport::actingAs($member);
        $this->deleteJson("/api/v1/scoring/groups/{$group->id}/participants/{$memberRow->id}")->assertOk();
        $this->assertSoftDeleted('scoring_sessions', ['id' => $memberRow->id]);
    }

    public function test_non_host_cannot_manage_or_view_and_gets_404(): void
    {
        $host = User::factory()->create();
        $stranger = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();
        $guest = ScoringSession::factory()->guest()->create(['scoring_session_group_id' => $group->id]);

        Passport::actingAs($stranger);

        $this->getJson("/api/v1/scoring/groups/{$group->id}")->assertNotFound();
        $this->postJson("/api/v1/scoring/groups/{$group->id}/participants", [
            'participants' => [['name' => 'Intruder']],
        ])->assertNotFound();
        $this->patchJson("/api/v1/scoring/groups/{$group->id}", ['title' => 'Hijacked'])->assertNotFound();
        $this->deleteJson("/api/v1/scoring/groups/{$group->id}/participants/{$guest->id}")->assertNotFound();

        // The roster row must survive the rejected delete.
        $this->assertDatabaseHas('scoring_sessions', ['id' => $guest->id, 'deleted_at' => null]);
    }
}
