<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 20 — Multi-Jarak Per-Peserta.
 *
 * The group format is only the default. Each participant may carry their real
 * distance and target-face so Pak Budi's 15m practice never becomes a false
 * 50m personal best.
 */
class GroupDistanceTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_join_accepts_distance_and_target_face_override(): void
    {
        $host = User::factory()->create();
        $budi = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create([
            'distance_category' => '50m',
            'distance_m' => 50,
            'target_face_cm' => 122,
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ]);

        Passport::actingAs($budi);
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", [
            'bow_class' => 'recurve',
            'distance_m' => 15,
            'target_face_cm' => 80,
        ])
            ->assertCreated()
            ->assertJsonPath('data.distance_category', '15m')
            ->assertJsonPath('data.distance_m', 15)
            ->assertJsonPath('data.target_face_cm', 80);

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $group->id,
            'user_id' => $budi->id,
            'distance_category' => '15m',
            'distance_m' => 15,
            'target_face_cm' => 80,
        ]);
    }

    public function test_host_or_owner_can_override_participant_distance_before_scoring(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create([
            'distance_category' => '50m',
            'distance_m' => 50,
            'target_face_cm' => 122,
        ]);
        $row = ScoringSession::factory()->for($member)->create([
            'scoring_session_group_id' => $group->id,
            'distance_category' => '50m',
            'distance_m' => 50,
            'target_face_cm' => 122,
            'arrows_shot' => 0,
        ]);

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$row->id}/distance", [
            'distance_m' => 30,
            'target_face_cm' => 80,
        ])
            ->assertOk()
            ->assertJsonPath('data.distance_category', '30m')
            ->assertJsonPath('data.distance_m', 30)
            ->assertJsonPath('data.target_face_cm', 80);

        Passport::actingAs($member);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$row->id}/distance", [
            'distance_m' => 15,
        ])
            ->assertOk()
            ->assertJsonPath('data.distance_category', '15m')
            ->assertJsonPath('data.distance_m', 15)
            ->assertJsonPath('data.target_face_cm', 80);
    }

    public function test_distance_override_is_blocked_after_score_exists(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();
        $row = ScoringSession::factory()->create([
            'scoring_session_group_id' => $group->id,
            'arrows_shot' => 3,
            'distance_category' => '50m',
            'distance_m' => 50,
        ]);

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$row->id}/distance", [
            'distance_m' => 15,
        ])->assertStatus(422);

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $row->id,
            'distance_category' => '50m',
            'distance_m' => 50,
        ]);
    }

    public function test_leaderboard_ranks_archers_inside_their_own_distance_group(): void
    {
        $host = User::factory()->create();
        Passport::actingAs($host);

        $groupId = $this->postJson('/api/v1/scoring/groups', [
            'title' => 'Multi Jarak',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ])->assertCreated()->json('data.id');

        $rows = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [
                ['name' => 'Sinta 50', 'distance_m' => 50, 'target_face_cm' => 122],
                ['name' => 'Andi 50', 'distance_m' => 50, 'target_face_cm' => 122],
                ['name' => 'Pak Budi 15', 'distance_m' => 15, 'target_face_cm' => 80],
            ],
        ])->assertCreated()->json('data');

        $ids = array_column($rows, 'id');
        $this->score($groupId, $ids[0], [[8, 8, 8]]);
        $this->score($groupId, $ids[1], [[10, 10, 10]]);
        $this->score($groupId, $ids[2], [[9, 9, 9]]);

        $entries = $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")
            ->assertOk()
            ->assertJsonPath('meta.distance_groups.0.distance_m', 15)
            ->assertJsonPath('meta.distance_groups.1.distance_m', 50)
            ->json('data.entries');

        $byId = collect($entries)->keyBy('session_id');

        $this->assertSame(1, $byId[$ids[1]]['rank']); // best 50m
        $this->assertSame(2, $byId[$ids[0]]['rank']); // second 50m
        $this->assertSame(1, $byId[$ids[2]]['rank']); // best 15m, not mixed with 50m
        $this->assertSame('15m / 80cm', $byId[$ids[2]]['distance_label']);
        $this->assertSame(1, $byId[$ids[2]]['distance_group_size']);
    }

    public function test_claimed_guest_preserves_real_distance_for_personal_best(): void
    {
        $host = User::factory()->create();
        Passport::actingAs($host);

        $groupId = $this->postJson('/api/v1/scoring/groups', [
            'title' => 'Pak Budi Datang',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ])->assertCreated()->json('data.id');

        $sessionId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [[
                'name' => 'Pak Budi',
                'bow_class' => 'recurve',
                'distance_m' => 15,
                'target_face_cm' => 80,
            ]],
        ])->assertCreated()->json('data.0.id');

        $this->score($groupId, $sessionId, [[10, 10, 10]], true);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])->assertOk();

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $sessionId,
            'user_id' => $budi->id,
            'distance_category' => '15m',
            'distance_m' => 15,
            'target_face_cm' => 80,
            'is_personal_best' => true,
        ]);
        $this->assertDatabaseHas('personal_bests', [
            'user_id' => $budi->id,
            'bow_class' => 'recurve',
            'distance_category' => '15m',
            'best_score' => 30,
        ]);
        $this->assertDatabaseMissing('personal_bests', [
            'user_id' => $budi->id,
            'bow_class' => 'recurve',
            'distance_category' => '50m',
            'best_score' => 30,
        ]);
    }

    /**
     * @param  array<int, array<int, int>>  $ends
     */
    private function score(string $groupId, string $sessionId, array $ends, bool $completed = false): void
    {
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/score", [
            'status' => $completed ? 'completed' : null,
            'ends' => $this->endsPayload($ends),
        ])->assertOk();
    }

    /**
     * @param  array<int, array<int, int>>  $ends
     * @return array<int, array<string, mixed>>
     */
    private function endsPayload(array $ends): array
    {
        $payload = [];
        foreach ($ends as $endIndex => $arrows) {
            $payload[] = [
                'end_number' => $endIndex + 1,
                'arrows' => array_map(
                    static fn (int $score, int $index): array => [
                        'arrow_index' => $index,
                        'score_value' => $score,
                        'is_x' => false,
                        'is_miss' => $score === 0,
                    ],
                    $arrows,
                    array_keys($arrows),
                ),
            ];
        }

        return $payload;
    }
}
