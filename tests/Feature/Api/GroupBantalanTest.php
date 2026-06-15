<?php

namespace Tests\Feature\Api;

use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 16 — Entitas Bantalan & Pemetaan Peserta (Phase 3, backend).
 *
 * The bantalan is the unit of parallel work (Efisiensi E1): turning the flat
 * roster into per-butt buckets is the prerequisite for many scorers (Sprint 17)
 * and O(constant) throughput. Covers wiring the butt into the participant/join
 * contract (16.1), moving a participant between butts (16.2), the round-robin
 * auto-distribute (16.3), the grouped-by-butt roster (16.4) and that the butt
 * rides along when a guest slot is claimed (16.5).
 */
class GroupBantalanTest extends TestCase
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

    /** Host a group and return its id. */
    private function hostGroup(User $host): string
    {
        Passport::actingAs($host);

        return $this->postJson('/api/v1/scoring/groups', $this->groupPayload())
            ->assertCreated()->json('data.id');
    }

    /**
     * Quick-add named guests; returns the created session ids in order.
     *
     * @param  array<int, string>  $names
     * @return array<int, string>
     */
    private function addGuests(string $groupId, array $names): array
    {
        $participants = array_map(static fn (string $name): array => ['name' => $name], $names);

        return $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => $participants,
        ])->assertCreated()->json('data.*.id');
    }

    // --- 16.1 butt in the participant/join contract -------------------------

    public function test_quick_add_persists_and_returns_the_bantalan(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);

        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [
                ['name' => 'Rina', 'target_butt' => 3, 'target_letter' => 'a'],
            ],
        ])
            ->assertCreated()
            ->assertJsonPath('data.0.target_butt', 3)
            // Letter is normalized to uppercase for a stable contract.
            ->assertJsonPath('data.0.target_letter', 'A');

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $groupId,
            'guest_name' => 'Rina',
            'target_butt' => 3,
            'target_letter' => 'A',
        ]);
    }

    public function test_self_join_may_map_to_a_bantalan(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();

        Passport::actingAs($member = User::factory()->create());
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", [
            'target_butt' => 2,
            'target_letter' => 'B',
        ])
            ->assertCreated()
            ->assertJsonPath('data.target_butt', 2)
            ->assertJsonPath('data.target_letter', 'B');

        $this->assertDatabaseHas('scoring_sessions', [
            'scoring_session_group_id' => $group->id,
            'user_id' => $member->id,
            'target_butt' => 2,
            'target_letter' => 'B',
        ]);
    }

    public function test_self_join_back_fills_an_unmapped_butt_but_never_overwrites(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();

        Passport::actingAs($member = User::factory()->create());

        // First join with no butt → unmapped row.
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join")->assertCreated();

        // A later tap back-fills the butt onto the same (idempotent) row.
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", ['target_butt' => 4, 'target_letter' => 'C'])
            ->assertCreated()
            ->assertJsonPath('data.target_butt', 4)
            ->assertJsonPath('data.target_letter', 'C');

        // Yet another tap with a different butt must NOT overwrite the mapping.
        $this->postJson("/api/v1/scoring/groups/{$group->id}/join", ['target_butt' => 9])
            ->assertCreated()
            ->assertJsonPath('data.target_butt', 4);

        $this->assertSame(1, $group->participants()->count());
        $this->assertDatabaseHas('scoring_sessions', [
            'user_id' => $member->id,
            'target_butt' => 4,
            'target_letter' => 'C',
        ]);
    }

    // --- 16.2 move a participant between bantalan ---------------------------

    public function test_host_moves_a_participant_between_butts(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);
        [$rinaId] = $this->addGuests($groupId, ['Rina']);

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/groups/{$groupId}/participants/{$rinaId}/butt", [
            'target_butt' => 5,
            'target_letter' => 'd',
        ])
            ->assertOk()
            ->assertJsonPath('data.target_butt', 5)
            ->assertJsonPath('data.target_letter', 'D');

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $rinaId,
            'target_butt' => 5,
            'target_letter' => 'D',
        ]);
    }

    public function test_null_butt_un_maps_a_participant_and_clears_the_letter(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();
        $guest = ScoringSession::factory()->guest()->create([
            'scoring_session_group_id' => $group->id,
            'target_butt' => 2,
            'target_letter' => 'A',
        ]);

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$guest->id}/butt", [
            'target_butt' => null,
        ])
            ->assertOk()
            ->assertJsonPath('data.target_butt', null)
            ->assertJsonPath('data.target_letter', null);

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $guest->id,
            'target_butt' => null,
            'target_letter' => null,
        ]);
    }

    public function test_member_moves_own_row_but_a_stranger_cannot(): void
    {
        $host = User::factory()->create();
        $member = User::factory()->create();
        $stranger = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();
        $row = ScoringSession::factory()->for($member)->create(['scoring_session_group_id' => $group->id]);

        // The owner may move their own row.
        Passport::actingAs($member);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$row->id}/butt", [
            'target_butt' => 1,
        ])->assertOk()->assertJsonPath('data.target_butt', 1);

        // A stranger gets a privacy 404 and the row is untouched.
        Passport::actingAs($stranger);
        $this->patchJson("/api/v1/scoring/groups/{$group->id}/participants/{$row->id}/butt", [
            'target_butt' => 8,
        ])->assertNotFound();

        $this->assertDatabaseHas('scoring_sessions', ['id' => $row->id, 'target_butt' => 1]);
    }

    // --- 16.3 round-robin auto-distribute ----------------------------------

    public function test_auto_distribute_deals_the_roster_evenly_round_robin(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);
        $ids = $this->addGuests($groupId, ['A1', 'A2', 'A3', 'A4', 'A5']);

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/auto-distribute", ['butt_count' => 2])
            ->assertOk()
            ->assertJsonCount(5, 'data');

        // Round-robin by insertion order: butt = (i % 2) + 1, letter advances
        // each lap. 5 archers / 2 butts ⇒ butt 1 = {A,B,C}, butt 2 = {A,B}.
        $expected = [
            [$ids[0], 1, 'A'],
            [$ids[1], 2, 'A'],
            [$ids[2], 1, 'B'],
            [$ids[3], 2, 'B'],
            [$ids[4], 1, 'C'],
        ];
        foreach ($expected as [$id, $butt, $letter]) {
            $this->assertDatabaseHas('scoring_sessions', [
                'id' => $id,
                'target_butt' => $butt,
                'target_letter' => $letter,
            ]);
        }

        // Counts differ by at most one — the whole point of round-robin.
        $counts = ScoringSession::query()
            ->where('scoring_session_group_id', $groupId)
            ->selectRaw('target_butt, count(*) as c')
            ->groupBy('target_butt')->pluck('c', 'target_butt');
        $this->assertSame(3, (int) $counts[1]);
        $this->assertSame(2, (int) $counts[2]);
    }

    public function test_auto_distribute_refuses_to_overflow_capacity(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);
        $this->addGuests($groupId, ['A1', 'A2', 'A3', 'A4', 'A5']);

        // 2 butts × capacity 2 = 4 seats < 5 archers ⇒ refuse rather than cram.
        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/auto-distribute", [
            'butt_count' => 2,
            'capacity' => 2,
        ])->assertStatus(422);

        // Nothing was mapped by the rejected call.
        $this->assertSame(
            0,
            ScoringSession::query()->where('scoring_session_group_id', $groupId)
                ->whereNotNull('target_butt')->count(),
        );
    }

    public function test_auto_distribute_on_an_empty_roster_is_a_422(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);

        Passport::actingAs($host);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/auto-distribute", ['butt_count' => 3])
            ->assertStatus(422);
    }

    public function test_only_the_host_may_auto_distribute(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);
        $this->addGuests($groupId, ['A1', 'A2']);

        Passport::actingAs(User::factory()->create());
        $this->postJson("/api/v1/scoring/groups/{$groupId}/auto-distribute", ['butt_count' => 2])
            ->assertNotFound();
    }

    // --- 16.4 grouped-by-butt roster ---------------------------------------

    public function test_roster_can_be_grouped_per_bantalan(): void
    {
        $host = User::factory()->create();
        $groupId = $this->hostGroup($host);
        $ids = $this->addGuests($groupId, ['A1', 'A2', 'A3', 'A4', 'A5']);

        Passport::actingAs($host);
        // Map four onto two butts; leave the fifth unmapped.
        $this->patchJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[0]}/butt", ['target_butt' => 1, 'target_letter' => 'A'])->assertOk();
        $this->patchJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[1]}/butt", ['target_butt' => 1, 'target_letter' => 'B'])->assertOk();
        $this->patchJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[2]}/butt", ['target_butt' => 2, 'target_letter' => 'A'])->assertOk();
        $this->patchJson("/api/v1/scoring/groups/{$groupId}/participants/{$ids[3]}/butt", ['target_butt' => 2, 'target_letter' => 'B'])->assertOk();

        $response = $this->getJson("/api/v1/scoring/groups/{$groupId}/butts")
            ->assertOk()
            ->assertJsonPath('meta.butt_count', 2)
            ->assertJsonPath('meta.mapped_count', 4)
            ->assertJsonPath('meta.unmapped_count', 1)
            ->assertJsonPath('meta.participant_count', 5);

        // Buttons ascending, unmapped bucket last.
        $response->assertJsonPath('data.butts.0.target_butt', 1)
            ->assertJsonPath('data.butts.0.participant_count', 2)
            ->assertJsonPath('data.butts.1.target_butt', 2)
            ->assertJsonPath('data.butts.2.target_butt', null)
            ->assertJsonPath('data.butts.2.participant_count', 1);
    }

    public function test_grouped_roster_requires_view_access(): void
    {
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create();

        Passport::actingAs(User::factory()->create());
        $this->getJson("/api/v1/scoring/groups/{$group->id}/butts")->assertNotFound();
    }

    // --- 16.5 the butt rides along when a slot is claimed -------------------

    public function test_bantalan_is_preserved_when_a_guest_slot_is_claimed(): void
    {
        // A guest mapped to butt 3/'C' is scored, then claimed & approved; the
        // butt must travel with the slot into the new owner's history.
        $host = User::factory()->create();
        Passport::actingAs($host);
        $groupId = $this->postJson('/api/v1/scoring/groups', $this->groupPayload([
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ]))->assertCreated()->json('data.id');

        $sessionId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [['name' => 'Pak Budi', 'bow_class' => 'recurve', 'target_butt' => 3, 'target_letter' => 'C']],
        ])->assertCreated()->json('data.0.id');

        $this->putJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/score", [
            'status' => 'completed',
            'ends' => [[
                'end_number' => 1,
                'arrows' => [
                    ['arrow_index' => 0, 'score_value' => 9, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 1, 'score_value' => 9, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 2, 'score_value' => 9, 'is_x' => false, 'is_miss' => false],
                ],
            ]],
        ])->assertOk();

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])->assertOk();

        // Ownership flipped, but the bantalan is exactly as the archer shot it.
        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $sessionId,
            'user_id' => $budi->id,
            'target_butt' => 3,
            'target_letter' => 'C',
        ]);
    }
}
