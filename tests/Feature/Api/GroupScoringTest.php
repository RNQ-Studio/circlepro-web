<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\User;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 03 — Skoring Peserta (Offline-Sync) & Leaderboard Adil (Phase 0).
 *
 * Covers: idempotent participant scoring through the shared pipeline (3.1/3.2),
 * forgiving batch sync (3.3), the fair aggregate leaderboard with tie-break
 * total → x → ten → SERI (3.4), live fairness on a common number of validated
 * rounds (3.5), the meta.version polling cursor (3.6), and the guest-integrity
 * guarantee that a guest never touches anyone's stats/PB/gamification (3.7).
 */
class GroupScoringTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  array<string, mixed>  $overrides
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
            'num_ends' => 3,
            'arrows_per_end' => 3,
        ], $overrides);
    }

    /**
     * Build an ends payload from a compact spec. Each end is an array of arrows;
     * an arrow is either an int score or ['v' => int, 'x' => bool].
     *
     * @param  array<int, array<int, int|array{v:int, x?:bool}>>  $ends
     * @return array<int, array<string, mixed>>
     */
    private function endsPayload(array $ends): array
    {
        $payload = [];
        foreach ($ends as $endIndex => $arrows) {
            $rows = [];
            foreach (array_values($arrows) as $arrowIndex => $arrow) {
                $value = is_array($arrow) ? $arrow['v'] : $arrow;
                $rows[] = [
                    'arrow_index' => $arrowIndex,
                    'score_value' => $value,
                    'is_x' => is_array($arrow) ? ($arrow['x'] ?? false) : false,
                    'is_miss' => $value === 0,
                ];
            }
            $payload[] = ['end_number' => $endIndex + 1, 'arrows' => $rows];
        }

        return $payload;
    }

    /**
     * Host a group and quick-add the given guest names; returns [groupId, ids].
     *
     * @param  array<int, string>  $names
     * @return array{0: string, 1: array<int, string>}
     */
    private function hostGroupWithGuests(User $host, array $names, array $overrides = []): array
    {
        Passport::actingAs($host);
        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload($overrides))
            ->json('data.id');

        $participants = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => array_map(fn (string $name): array => ['name' => $name], $names),
        ])->json('data');

        return [$groupId, array_column($participants, 'id')];
    }

    public function test_host_scores_a_guest_and_aggregates_are_recomputed(): void
    {
        [$groupId, $ids] = $this->hostGroupWithGuests(User::factory()->create(), ['Pak Budi']);

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[0]}/score", [
            'status' => 'completed',
            'ends' => $this->endsPayload([[9, 9, 9], [10, 10, 8], [10, 9, 9]]),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_score', 83)
            ->assertJsonPath('data.arrows_shot', 9)
            ->assertJsonPath('data.ten_count', 3)
            ->assertJsonPath('data.x_count', 0)
            ->assertJsonPath('data.status', 'completed')
            // A guest never earns a personal best (§3.2 binder integrity).
            ->assertJsonPath('data.is_personal_best', false);
    }

    public function test_scoring_is_idempotent_on_retry(): void
    {
        [$groupId, $ids] = $this->hostGroupWithGuests(User::factory()->create(), ['Budi']);

        $body = [
            'status' => 'completed',
            'client_uuid' => (string) Str::uuid(),
            'ends' => $this->endsPayload([[9, 9, 9], [9, 9, 9], [9, 9, 9]]),
        ];

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[0]}/score", $body)
            ->assertOk()->assertJsonPath('data.total_score', 81);
        // A retry (lost ACK) must not duplicate ends nor change the total.
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[0]}/score", $body)
            ->assertOk()->assertJsonPath('data.total_score', 81);

        $this->assertSame(3, ScoringSession::find($ids[0])->ends()->count());
        $this->assertSame(9, (int) ScoringSession::find($ids[0])->arrows_shot);
    }

    public function test_owned_participant_completion_earns_pb_and_gamification(): void
    {
        $host = User::factory()->create();
        Passport::actingAs($host);
        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload([
            'host_participates' => true,
            'host_bow_class' => 'recurve',
            'num_ends' => 1,
        ]))->json('data.id');

        $ownRow = ScoringSession::where('scoring_session_group_id', $groupId)
            ->where('user_id', $host->id)->firstOrFail();

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$ownRow->id}/score", [
            'status' => 'completed',
            'ends' => $this->endsPayload([[10, 10, 10]]),
        ])
            ->assertOk()
            ->assertJsonPath('data.total_score', 30)
            ->assertJsonPath('data.is_personal_best', true);

        // Owned rows ARE the user's own sessions: PB + gamification fire.
        $this->assertDatabaseHas('personal_bests', [
            'user_id' => $host->id,
            'bow_class' => 'recurve',
            'best_score' => 30,
        ]);
        $this->assertDatabaseHas('user_stats', ['user_id' => $host->id]);
    }

    public function test_guest_score_never_pollutes_stats_dashboard_pb_or_gamification(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Tamu Satu', 'Tamu Dua']);

        foreach ($ids as $id) {
            $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$id}/score", [
                'status' => 'completed',
                'ends' => $this->endsPayload([[10, 10, 10], [10, 10, 10], [10, 10, 10]]),
            ])->assertOk();
        }

        // No personal best, no gamification stat, no gamification anywhere.
        $this->assertDatabaseCount('personal_bests', 0);
        $this->assertDatabaseMissing('user_stats', ['user_id' => $host->id]);
        $this->assertDatabaseCount('user_stats', 0);

        // The host's own scoring history & dashboard stay empty — guest rows
        // (user_id NULL) are filtered out of every stats query (§3.2).
        Passport::actingAs($host);
        $this->getJson('/api/v1/scoring/sessions')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/scoring/dashboard')
            ->assertOk()
            ->assertJsonPath('data.total_sessions', 0)
            ->assertJsonPath('data.total_arrows', 0);
    }

    public function test_only_host_or_owner_may_score_a_row(): void
    {
        $host = User::factory()->create();
        $stranger = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Budi']);

        // A stranger (not host, not participant) is invisible to the group: 404.
        Passport::actingAs($stranger);
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[0]}/score", [
            'ends' => $this->endsPayload([[9, 9, 9]]),
        ])->assertNotFound();
    }

    public function test_batch_sync_is_forgiving_and_idempotent(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Budi', 'Andi']);

        $batch = [
            'sessions' => [
                ['id' => $ids[0], 'status' => 'completed', 'ends' => $this->endsPayload([[9, 9, 9], [9, 9, 9], [9, 9, 9]])],
                ['id' => $ids[1], 'status' => 'completed', 'ends' => $this->endsPayload([[8, 8, 8], [8, 8, 8], [8, 8, 8]])],
            ],
        ];

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", $batch)
            ->assertOk()
            ->assertJsonCount(2, 'data');
        // Re-send the whole batch (flaky connection): no duplicates, same totals.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", $batch)->assertOk();

        $this->assertSame(81, (int) ScoringSession::find($ids[0])->total_score);
        $this->assertSame(72, (int) ScoringSession::find($ids[1])->total_score);
        $this->assertSame(3, ScoringSession::find($ids[0])->ends()->count());
    }

    public function test_batch_sync_mints_an_offline_created_guest_row(): void
    {
        $host = User::factory()->create();
        [$groupId] = $this->hostGroupWithGuests($host, ['Existing']);

        $offlineId = (string) Str::ulid();

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", [
            'sessions' => [[
                'id' => $offlineId,
                'name' => 'Dibuat Offline',
                'status' => 'completed',
                'ends' => $this->endsPayload([[7, 7, 7], [7, 7, 7], [7, 7, 7]]),
            ]],
        ])->assertOk();

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $offlineId,
            'scoring_session_group_id' => $groupId,
            'guest_name' => 'Dibuat Offline',
            'user_id' => null,
            'total_score' => 63,
        ]);
    }

    public function test_non_host_participant_cannot_sync_a_row_they_do_not_own(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Tamu']);

        // The member self-joins by getting an owned row added by the host flow;
        // here we attach a member-owned row directly for the scenario.
        $memberRow = ScoringSession::factory()->for($member)->create([
            'scoring_session_group_id' => $groupId,
            'num_ends' => 3,
            'arrows_per_end' => 3,
        ]);

        Passport::actingAs($member);
        // The member may view the group (participant) but may not write a guest.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", [
            'sessions' => [['id' => $ids[0], 'ends' => $this->endsPayload([[9, 9, 9]])]],
        ])->assertStatus(403);

        // ...but may sync their own row just fine.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/sync", [
            'sessions' => [['id' => $memberRow->id, 'ends' => $this->endsPayload([[9, 9, 9], [9, 9, 9], [9, 9, 9]])]],
        ])->assertOk();
    }

    public function test_leaderboard_tie_break_is_total_then_x_then_ten(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Xman', 'Tenner', 'Lower'], ['num_ends' => 1]);

        // Same total (30) → X breaks the tie; Lower trails on total.
        $this->scoreCompleted($groupId, $ids[0], [[['v' => 10, 'x' => true], 10, 10]]); // 30, x1, ten3
        $this->scoreCompleted($groupId, $ids[1], [[10, 10, 10]]);                       // 30, x0, ten3
        $this->scoreCompleted($groupId, $ids[2], [[9, 9, 9]]);                           // 27

        $entries = $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")
            ->assertOk()
            ->assertJsonPath('meta.all_completed', true)
            ->json('data.entries');

        $this->assertSame($ids[0], $entries[0]['session_id']);
        $this->assertSame(1, $entries[0]['rank']);
        $this->assertSame($ids[1], $entries[1]['session_id']);
        $this->assertSame(2, $entries[1]['rank']);
        $this->assertSame($ids[2], $entries[2]['session_id']);
        $this->assertSame(3, $entries[2]['rank']);
        $this->assertFalse($entries[0]['tied']);
    }

    public function test_leaderboard_marks_a_genuine_tie_as_seri(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['A', 'B'], ['num_ends' => 1]);

        // Identical total/x/ten → SERI: both share rank 1.
        $this->scoreCompleted($groupId, $ids[0], [[10, 10, 9]]);
        $this->scoreCompleted($groupId, $ids[1], [[10, 10, 9]]);

        $entries = $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")
            ->assertOk()->json('data.entries');

        $this->assertSame(1, $entries[0]['rank']);
        $this->assertSame(1, $entries[1]['rank']);
        $this->assertTrue($entries[0]['tied']);
        $this->assertTrue($entries[1]['tied']);
    }

    public function test_live_leaderboard_compares_on_equal_validated_rounds(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Fast', 'Better'], ['num_ends' => 3]);

        // Fast shoots all 3 rounds but mediocre (raw total 54, leading on total).
        $this->scoreInProgress($groupId, $ids[0], [[6, 6, 6], [6, 6, 6], [6, 6, 6]]);
        // Better shoots just 1 round, but perfectly (raw total 30).
        $this->scoreInProgress($groupId, $ids[1], [[10, 10, 10]]);

        $response = $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")->assertOk();

        // The board is honest: while live, it compares at 1 common round, so the
        // better-but-slower shooter leads despite a lower raw total (K3).
        $response
            ->assertJsonPath('meta.all_completed', false)
            ->assertJsonPath('meta.is_provisional', true)
            ->assertJsonPath('meta.comparable_ends', 1)
            ->assertJsonPath('meta.target_ends', 3);

        $entries = $response->json('data.entries');
        $this->assertSame($ids[1], $entries[0]['session_id']);   // Better
        $this->assertSame(1, $entries[0]['rank']);
        $this->assertSame(30, $entries[0]['total_score']);
        $this->assertTrue($entries[0]['is_provisional_leader']);

        $this->assertSame($ids[0], $entries[1]['session_id']);   // Fast (higher raw total)
        $this->assertSame(54, $entries[1]['total_score']);
        $this->assertSame(2, $entries[1]['rank']);
        $this->assertSame(3, $entries[1]['validated_ends']);
    }

    public function test_leaderboard_version_short_circuits_when_unchanged(): void
    {
        $host = User::factory()->create();
        [$groupId, $ids] = $this->hostGroupWithGuests($host, ['Budi'], ['num_ends' => 1]);
        $this->scoreCompleted($groupId, $ids[0], [[9, 9, 9]]);

        $version = $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")
            ->assertOk()->json('meta.version');
        $this->assertNotNull($version);

        // Same version → cheap empty payload (polling foundation for Phase 1).
        $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard?version={$version}")
            ->assertOk()
            ->assertJsonPath('meta.unchanged', true)
            ->assertJsonCount(0, 'data.entries');

        // A roster change moves the version → the stale cursor returns the board.
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [['name' => 'Pendatang']],
        ])->assertCreated();

        $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard?version={$version}")
            ->assertOk()
            ->assertJsonMissingPath('meta.unchanged')
            ->assertJsonCount(2, 'data.entries');
    }

    public function test_leaderboard_is_private_to_host_and_participants(): void
    {
        $host = User::factory()->create();
        [$groupId] = $this->hostGroupWithGuests($host, ['Budi']);

        Passport::actingAs(User::factory()->create());
        $this->getJson("/api/v1/scoring/groups/{$groupId}/leaderboard")->assertNotFound();
    }

    /**
     * @param  array<int, array<int, int|array{v:int, x?:bool}>>  $ends
     */
    private function scoreCompleted(string $groupId, string $sessionId, array $ends): void
    {
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/score", [
            'status' => ScoringSessionStatus::Completed->value,
            'ends' => $this->endsPayload($ends),
        ])->assertOk();
    }

    /**
     * @param  array<int, array<int, int|array{v:int, x?:bool}>>  $ends
     */
    private function scoreInProgress(string $groupId, string $sessionId, array $ends): void
    {
        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/score", [
            'ends' => $this->endsPayload($ends),
        ])->assertOk();
    }
}
