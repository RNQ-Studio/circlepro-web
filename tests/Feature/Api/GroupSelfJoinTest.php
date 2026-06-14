<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ParticipationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 10 — Self-Join & Self-Scoring (Phase 1, the join endpoint activated for
 * the mobile self-join flow).
 *
 * Covers: a "real" user joining for themselves as an owned `self` row so consent
 * is automatic (K7), the join being idempotent (double-tap safe), the optional
 * bow class (K8) that back-fills onto a row that lacked one, the closed-session
 * guard, and the end-to-end promise — once joined a member may view, score their
 * own row (earning a real PB, binder model §1) and leave on their own.
 */
class GroupSelfJoinTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroup(User $host, array $overrides = []): ScoringSessionGroup
    {
        return ScoringSessionGroup::factory()->for($host, 'host')->create(array_merge([
            'distance_category' => '50m',
            'distance_m' => 50,
            'num_ends' => 1,
            'arrows_per_end' => 3,
            'target_face_cm' => 122,
        ], $overrides));
    }

    public function test_a_user_self_joins_as_an_owned_self_row(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create();
        $group = $this->makeGroup($host);

        Passport::actingAs($andi);
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", ['bow_class' => 'recurve'])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $andi->id)
            ->assertJsonPath('data.is_guest', false)
            ->assertJsonPath('data.participation_status', ParticipationStatus::Self->value)
            ->assertJsonPath('data.bow_class', 'recurve')
            ->assertJsonPath('data.display_name', $andi->name);

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $group->id,
            'user_id' => $andi->id,
            'participation_status' => ParticipationStatus::Self->value,
            // Joined member is created by themselves (self-added, not host).
            'added_by_user_id' => $andi->id,
            // Round format is inherited from the group (K8: metadata optional).
            'distance_m' => 50,
            'num_ends' => 1,
        ]);
    }

    public function test_self_join_is_idempotent_on_a_double_tap(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create();
        $group = $this->makeGroup($host);

        Passport::actingAs($andi);
        $first = $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertCreated()->json('data.id');
        $second = $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertCreated()->json('data.id');

        $this->assertSame($first, $second);
        $this->assertSame(1, $group->participants()->where('user_id', $andi->id)->count());
    }

    public function test_optional_bow_class_back_fills_a_row_that_lacked_one(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create();
        $group = $this->makeGroup($host);

        Passport::actingAs($andi);
        // Join without a bow class (K8: must not be blocked).
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertCreated()
            ->assertJsonPath('data.bow_class', null);

        // A later tap supplies the class → back-filled onto the same row.
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", ['bow_class' => 'compound'])
            ->assertCreated()
            ->assertJsonPath('data.bow_class', 'compound');

        $this->assertSame(1, $group->participants()->where('user_id', $andi->id)->count());
    }

    public function test_self_join_is_blocked_on_a_finished_group(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->completed()->create();

        Passport::actingAs(User::factory()->create());
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertStatus(422);

        $this->assertSame(0, $group->participants()->count());
    }

    public function test_self_join_requires_authentication(): void
    {
        $group = $this->makeGroup(User::factory()->create());

        $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertUnauthorized();
    }

    public function test_self_joined_member_can_view_score_their_own_row_and_earn_pb(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create();
        $group = $this->makeGroup($host);

        Passport::actingAs($andi);
        $sessionId = $this->postJson("/api/v1/scoring/groups/{$group->id}/join", ['bow_class' => 'recurve'])
            ->assertCreated()->json('data.id');

        // A joined member becomes a participant → may view the group & board.
        $this->getJson("/api/v1/scoring/groups/{$group->id}")->assertOk();

        // ...and may score their OWN row (binder model: a real, owned session).
        $this->putJson("/api/v1/scoring/groups/{$group->id}/participants/{$sessionId}/score", [
            'status' => 'completed',
            'ends' => [[
                'end_number' => 1,
                'arrows' => [
                    ['arrow_index' => 0, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 1, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 2, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                ],
            ]],
        ])
            ->assertOk()
            ->assertJsonPath('data.total_score', 30)
            // Owned self row → it really is the user's session: PB fires (§1).
            ->assertJsonPath('data.is_personal_best', true);

        $this->assertDatabaseHas('personal_bests', [
            'user_id' => $andi->id,
            'bow_class' => 'recurve',
            'best_score' => 30,
        ]);
        $this->assertDatabaseHas('user_stats', ['user_id' => $andi->id]);
    }

    public function test_self_joined_member_can_leave_on_their_own(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create();
        $group = $this->makeGroup($host);

        Passport::actingAs($andi);
        $sessionId = $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/scoring/groups/{$group->id}/participants/{$sessionId}")
            ->assertOk();

        $this->assertSoftDeleted('scoring_sessions', ['id' => $sessionId]);

        // After leaving, a fresh join mints a new row (the old one is gone).
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join")
            ->assertCreated();
        $this->assertSame(
            1,
            $group->participants()->where('user_id', $andi->id)->whereNull('deleted_at')->count(),
        );
    }
}
