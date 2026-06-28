<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 17 — Peran Skorer Per-Bantalan.
 *
 * A busy field is parallel: one scorer writes one bantalan. These tests lock
 * the backend contract for assigning/claiming scorers, authorizing writes only
 * on the assigned butt, keeping independent butts unblocked, and recording the
 * scorer who last wrote a participant row.
 */
class GroupScorerTest extends TestCase
{
    use RefreshDatabase;

    private function makeGroupWithMappedGuests(User $host): array
    {
        Passport::actingAs($host);

        $groupId = $this->postJson('/api/v1/scoring/groups', [
            'title' => 'Latihan Gabungan',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 2,
            'arrows_per_end' => 3,
        ])->assertCreated()->json('data.id');

        $rows = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [
                ['name' => 'Bantalan 1 A', 'target_butt' => 1, 'target_letter' => 'A'],
                ['name' => 'Bantalan 1 B', 'target_butt' => 1, 'target_letter' => 'B'],
                ['name' => 'Bantalan 2 A', 'target_butt' => 2, 'target_letter' => 'A'],
            ],
        ])->assertCreated()->json('data');

        return [$groupId, array_column($rows, 'id')];
    }

    public function test_host_assigns_a_scorer_to_one_bantalan(): void
    {
        $host = User::factory()->create();
        $scorer = User::factory()->create(['name' => 'Skorer Satu']);
        [$groupId] = $this->makeGroupWithMappedGuests($host);

        Passport::actingAs($host);

        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorer->id,
            'target_butt' => 1,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $scorer->id)
            ->assertJsonPath('data.target_butt', 1)
            ->assertJsonPath('data.assignment_type', 'assigned')
            ->assertJsonPath('data.scorer.name', 'Skorer Satu');

        $this->assertDatabaseHas('group_scorers', [
            'scoring_session_group_id' => $groupId,
            'user_id' => $scorer->id,
            'target_butt' => 1,
            'assignment_type' => 'assigned',
            'assigned_by_user_id' => $host->id,
        ]);
    }

    public function test_participant_claims_an_unassigned_bantalan_once(): void
    {
        $host = User::factory()->create();
        $andi = User::factory()->create(['name' => 'Andi']);
        $sinta = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();

        ScoringSession::factory()->for($andi)->create([
            'scoring_session_group_id' => $group->id,
            'target_butt' => 2,
            'target_letter' => 'A',
        ]);
        ScoringSession::factory()->for($sinta)->create([
            'scoring_session_group_id' => $group->id,
            'target_butt' => 2,
            'target_letter' => 'B',
        ]);

        Passport::actingAs($andi);
        $this->postJson("/api/v1/scoring/groups/{$group->id}/scorers/claim", [
            'target_butt' => 2,
        ])
            ->assertCreated()
            ->assertJsonPath('data.user_id', $andi->id)
            ->assertJsonPath('data.assignment_type', 'claimed');

        Passport::actingAs($sinta);
        $this->postJson("/api/v1/scoring/groups/{$group->id}/scorers/claim", [
            'target_butt' => 2,
        ])->assertStatus(422);
    }

    public function test_scorer_may_write_only_their_assigned_bantalan_and_is_audited(): void
    {
        $host = User::factory()->create();
        $scorer = User::factory()->create();
        [$groupId, $rows] = $this->makeGroupWithMappedGuests($host);

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorer->id,
            'target_butt' => 1,
        ])->assertCreated();

        Passport::actingAs($scorer);
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$rows[0]}/score", [
            'ends' => $this->endsPayload([[9, 9, 9]]),
        ])
            ->assertOk()
            ->assertJsonPath('data.last_scored_by_user_id', $scorer->id)
            ->assertJsonPath('data.total_score', 27);

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$rows[2]}/score", [
            'ends' => $this->endsPayload([[10, 10, 10]]),
        ])->assertNotFound();

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $rows[0],
            'last_scored_by_user_id' => $scorer->id,
            'total_score' => 27,
        ]);
        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $rows[2],
            'last_scored_by_user_id' => null,
            'total_score' => 0,
        ]);
    }

    public function test_batch_sync_allows_parallel_scorers_on_different_butts(): void
    {
        $host = User::factory()->create();
        $scorerOne = User::factory()->create();
        $scorerTwo = User::factory()->create();
        [$groupId, $rows] = $this->makeGroupWithMappedGuests($host);

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorerOne->id,
            'target_butt' => 1,
        ])->assertCreated();
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorerTwo->id,
            'target_butt' => 2,
        ])->assertCreated();

        Passport::actingAs($scorerOne);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", [
            'sessions' => [
                ['id' => $rows[0], 'ends' => $this->endsPayload([[9, 9, 9]])],
                ['id' => $rows[1], 'ends' => $this->endsPayload([[8, 8, 8]])],
            ],
        ])->assertOk()->assertJsonCount(2, 'data');

        Passport::actingAs($scorerTwo);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", [
            'sessions' => [
                ['id' => $rows[2], 'ends' => $this->endsPayload([[10, 10, 10]])],
            ],
        ])->assertOk()->assertJsonCount(1, 'data');

        $this->assertDatabaseHas('scoring_sessions', ['id' => $rows[0], 'total_score' => 27]);
        $this->assertDatabaseHas('scoring_sessions', ['id' => $rows[1], 'total_score' => 24]);
        $this->assertDatabaseHas('scoring_sessions', ['id' => $rows[2], 'total_score' => 30]);
    }

    public function test_butt_status_reports_scorer_progress_and_lagging_butt(): void
    {
        $host = User::factory()->create();
        $scorerOne = User::factory()->create(['name' => 'Skorer 1']);
        $scorerTwo = User::factory()->create(['name' => 'Skorer 2']);
        [$groupId, $rows] = $this->makeGroupWithMappedGuests($host);

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorerOne->id,
            'target_butt' => 1,
        ])->assertCreated();
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $scorerTwo->id,
            'target_butt' => 2,
        ])->assertCreated();

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$rows[0]}/score", [
            'ends' => $this->endsPayload([[9, 9, 9], [9, 9, 9]]),
        ])->assertOk();
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$rows[1]}/score", [
            'ends' => $this->endsPayload([[8, 8, 8], [8, 8, 8]]),
        ])->assertOk();

        $response = $this->getJson("/api/v1/scoring/groups/{$groupId}/butts")
            ->assertOk()
            ->assertJsonPath('meta.group_status', 'in_progress')
            ->assertJsonPath('data.butts.0.target_butt', 1)
            ->assertJsonPath('data.butts.0.scorer.user_id', $scorerOne->id)
            ->assertJsonPath('data.butts.0.end_progress', 2)
            ->assertJsonPath('data.butts.0.is_lagging', false)
            ->assertJsonPath('data.butts.1.target_butt', 2)
            ->assertJsonPath('data.butts.1.scorer.user_id', $scorerTwo->id)
            ->assertJsonPath('data.butts.1.end_progress', 0)
            ->assertJsonPath('data.butts.1.lagging_by_ends', 2)
            ->assertJsonPath('data.butts.1.is_lagging', true);

        $version = $response->json('meta.version');
        $this->getJson("/api/v1/scoring/groups/{$groupId}/butts?version={$version}")
            ->assertOk()
            ->assertJsonPath('meta.unchanged', true)
            ->assertJsonCount(0, 'data.butts');

        $replacement = User::factory()->create(['name' => 'Skorer 2B']);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/scorers/assign", [
            'user_id' => $replacement->id,
            'target_butt' => 2,
        ])->assertCreated();

        $changed = $this->getJson("/api/v1/scoring/groups/{$groupId}/butts?version={$version}")
            ->assertOk()
            ->assertJsonPath('data.butts.1.scorer.user_id', $replacement->id);

        $this->assertNotSame($version, $changed->json('meta.version'));
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
