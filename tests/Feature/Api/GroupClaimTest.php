<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ClaimStatus;
use App\Support\Enums\ParticipationStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 13 — Klaim & Approval Host (Phase 2, loop akuisisi).
 *
 * Covers the moment a name becomes a user: a signed-in archer claims a guest
 * slot (13.1, anti double-claim 13.7), the host's context-rich inbox (13.2),
 * the transactional ownership transfer that births an HONEST PB at the real
 * distance — never a "PB 50m palsu" (13.3/13.4), the auto-reject of losing
 * claims + manual reject + self-cancel (13.5), and the deep-link notifications
 * to both sides (13.6).
 */
class GroupClaimTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Host a 50m group and quick-add one guest. By default the guest shoots at
     * the group's 50m, but a caller may override the slot's distance/face to
     * model an archer who actually shot a different distance (the PB-honesty
     * case). Returns [groupId, guestSessionId].
     *
     * @param  array<string, mixed>  $guest
     * @return array{0: string, 1: string}
     */
    private function hostGroupWithGuest(User $host, array $guest = []): array
    {
        Passport::actingAs($host);

        $groupId = $this->postJson('/api/v1/scoring/groups', [
            'title' => 'Latihan Sore Klub',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ])->assertCreated()->json('data.id');

        $sessionId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [array_merge(['name' => 'Pak Budi'], $guest)],
        ])->assertCreated()->json('data.0.id');

        return [$groupId, $sessionId];
    }

    /** Score a slot to completion (host acting). 3 arrows = total 27. */
    private function scoreSlotCompleted(string $groupId, string $sessionId): void
    {
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
    }

    // --- 13.1 / 13.7 submit & anti-abuse -----------------------------------

    public function test_a_signed_in_archer_can_claim_a_guest_slot(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        $budi = User::factory()->create();
        Passport::actingAs($budi);

        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim", [
            'message' => 'Ini aku, Budi yang di bantalan 3',
        ])
            ->assertCreated()
            ->assertJsonPath('data.status', ClaimStatus::Pending->value)
            ->assertJsonPath('data.claimant.id', $budi->id);

        $this->assertDatabaseHas('scoring_session_claims', [
            'scoring_session_id' => $sessionId,
            'claimant_user_id' => $budi->id,
            'status' => ClaimStatus::Pending->value,
        ]);

        // 13.6 — the host is notified with a deep-link payload.
        $note = Notification::query()->where('user_id', $host->id)
            ->where('type', 'group_claim_submitted')->first();
        $this->assertNotNull($note);
        $this->assertSame($groupId, $note->data['group_id']);
        $this->assertSame($sessionId, $note->data['session_id']);
    }

    public function test_a_guest_slot_cannot_be_double_claimed_by_the_same_archer(): void
    {
        [$groupId, $sessionId] = $this->hostGroupWithGuest(User::factory()->create());

        $budi = User::factory()->create();
        Passport::actingAs($budi);

        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated();
        // A second pending claim by the same archer is rejected (unique).
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertStatus(422);

        $this->assertSame(1, ScoringSession::find($sessionId)->claims()->count());
    }

    public function test_only_a_guest_slot_can_be_claimed(): void
    {
        $host = User::factory()->create();
        [$groupId] = $this->hostGroupWithGuest($host);

        // An owned (self) row, not a guest: a real user joins for themselves.
        $andi = User::factory()->create();
        Passport::actingAs($andi);
        $ownedId = $this->postJson("/api/v1/scoring/groups/{$groupId}/join")
            ->assertCreated()->json('data.id');

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$ownedId}/claim")
            ->assertStatus(422);
    }

    public function test_claim_requires_authentication(): void
    {
        // Build the slot directly (no Passport::actingAs) so the request is
        // genuinely unauthenticated.
        $group = ScoringSessionGroup::factory()->create();
        $session = ScoringSession::factory()->guest()->create([
            'scoring_session_group_id' => $group->id,
        ]);

        $this->postJson("/api/v1/scoring/groups/{$group->id}/participants/{$session->id}/claim")
            ->assertUnauthorized();
    }

    // --- 14.1 claimable guest slots (code-holder discovery) -----------------

    public function test_a_code_holder_can_list_claimable_guest_slots(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);
        $this->scoreSlotCompleted($groupId, $sessionId);

        // A fresh archer who only holds the code (never joined) can see the
        // guest slots, with their score, so they can find and claim theirs.
        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $this->getJson("/api/v1/scoring/groups/{$groupId}/claimable-slots")
            ->assertOk()
            ->assertJsonPath('data.slots.0.session_id', $sessionId)
            ->assertJsonPath('data.slots.0.display_name', 'Pak Budi')
            ->assertJsonPath('data.slots.0.total_score', 27)
            // Nothing claimed yet, so no badge.
            ->assertJsonPath('data.slots.0.my_claim_status', null);
    }

    public function test_claimable_slots_reflects_my_own_pending_claim(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        // The slot now carries MY claim status — the app paints "Menunggu
        // persetujuan host" without a second call.
        $this->getJson("/api/v1/scoring/groups/{$groupId}/claimable-slots")
            ->assertOk()
            ->assertJsonPath('data.slots.0.my_claim_status', ClaimStatus::Pending->value)
            ->assertJsonPath('data.slots.0.my_claim_id', $claimId);
    }

    public function test_claimable_slots_excludes_owned_rows(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        // An owned (self) row joins; it is not a guest, so it never appears.
        $andi = User::factory()->create();
        Passport::actingAs($andi);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/join")->assertCreated();

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $this->getJson("/api/v1/scoring/groups/{$groupId}/claimable-slots")
            ->assertOk()
            ->assertJsonCount(1, 'data.slots')
            ->assertJsonPath('data.slots.0.session_id', $sessionId);
    }

    public function test_claimable_slots_requires_authentication(): void
    {
        $group = ScoringSessionGroup::factory()->create();

        $this->getJson("/api/v1/scoring/groups/{$group->id}/claimable-slots")
            ->assertUnauthorized();
    }

    // --- 13.2 host inbox ----------------------------------------------------

    public function test_host_sees_a_context_rich_inbox(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);
        $this->scoreSlotCompleted($groupId, $sessionId);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated();

        Passport::actingAs($host);
        $this->getJson("/api/v1/scoring/groups/{$groupId}/claims")
            ->assertOk()
            ->assertJsonPath('data.0.status', ClaimStatus::Pending->value)
            ->assertJsonPath('data.0.claimant.id', $budi->id)
            // The host decides from memory: the slot's score travels with it.
            ->assertJsonPath('data.0.slot.total_score', 27)
            ->assertJsonPath('data.0.slot.display_name', 'Pak Budi');
    }

    public function test_a_non_host_cannot_see_the_inbox(): void
    {
        [$groupId, $sessionId] = $this->hostGroupWithGuest(User::factory()->create());

        $stranger = User::factory()->create();
        Passport::actingAs($stranger);
        $this->getJson("/api/v1/scoring/groups/{$groupId}/claims")
            ->assertNotFound();
    }

    // --- 13.3 / 13.4 transactional approve ----------------------------------

    public function test_approve_transfers_ownership_and_clears_guest_name(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host, ['bow_class' => 'recurve']);
        $this->scoreSlotCompleted($groupId, $sessionId);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', ClaimStatus::Approved->value);

        $this->assertDatabaseHas('scoring_sessions', [
            'id' => $sessionId,
            'user_id' => $budi->id,
            'guest_name' => null,
            'participation_status' => ParticipationStatus::Self->value,
        ]);
    }

    public function test_approve_births_an_honest_pb_at_the_real_distance(): void
    {
        // Group is at 50m, but Pak Budi actually shot at 15m. His slot keeps its
        // own distance, so the PB that is born must be a 15m PB — never a "PB 50m
        // palsu" that would haunt his graph.
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host, [
            'bow_class' => 'recurve',
            'distance_category' => '15m',
            'distance_m' => 15,
            'target_face_cm' => 80,
        ]);
        $this->scoreSlotCompleted($groupId, $sessionId);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.slot.is_personal_best', true);

        // The PB is at 15m...
        $this->assertDatabaseHas('personal_bests', [
            'user_id' => $budi->id,
            'bow_class' => 'recurve',
            'distance_category' => '15m',
            'best_score' => 27,
        ]);
        // ...and emphatically NOT at the group's 50m.
        $this->assertDatabaseMissing('personal_bests', [
            'user_id' => $budi->id,
            'distance_category' => '50m',
        ]);
        // Gamification (13.4): the new owner's stats row exists.
        $this->assertDatabaseHas('user_stats', ['user_id' => $budi->id]);

        // The claimant is notified with a deep-link payload (13.6).
        $note = Notification::query()->where('user_id', $budi->id)
            ->where('type', 'group_claim_approved')->first();
        $this->assertNotNull($note);
        $this->assertSame($claimId, $note->data['claim_id']);
    }

    public function test_approve_is_idempotent_against_a_second_approve(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host, ['bow_class' => 'recurve']);
        $this->scoreSlotCompleted($groupId, $sessionId);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])->assertOk();
        // A re-tap on an already-approved claim is a no-op error, not a re-transfer.
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertStatus(422);
    }

    // --- 13.5 reject / cancel ----------------------------------------------

    public function test_approve_auto_rejects_other_pending_claims_on_the_same_slot(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        $budi = User::factory()->create();
        $rangga = User::factory()->create();

        Passport::actingAs($budi);
        $budiClaim = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');
        Passport::actingAs($rangga);
        $ranggaClaim = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$budiClaim}", ['action' => 'approve'])->assertOk();

        // The losing claim is auto-rejected, and its claimant is notified.
        $this->assertDatabaseHas('scoring_session_claims', [
            'id' => $ranggaClaim,
            'status' => ClaimStatus::Rejected->value,
            'resolved_by_user_id' => $host->id,
        ]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $rangga->id,
            'type' => 'group_claim_rejected',
        ]);
    }

    public function test_host_can_reject_a_claim_by_hand(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'reject'])
            ->assertOk()
            ->assertJsonPath('data.status', ClaimStatus::Rejected->value);

        // The slot stays a guest — no ownership moved.
        $this->assertDatabaseHas('scoring_sessions', ['id' => $sessionId, 'user_id' => null]);
        $this->assertDatabaseHas('notifications', [
            'user_id' => $budi->id,
            'type' => 'group_claim_rejected',
        ]);
    }

    public function test_only_the_host_can_resolve_a_claim(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        // A stranger (and even the claimant) cannot approve — host-only (404).
        Passport::actingAs(User::factory()->create());
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertNotFound();
        Passport::actingAs($budi);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertNotFound();
    }

    public function test_claimant_can_cancel_then_re_claim(): void
    {
        [$groupId, $sessionId] = $this->hostGroupWithGuest(User::factory()->create());

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        $this->deleteJson("/api/v1/scoring/claims/{$claimId}")->assertOk();
        $this->assertDatabaseHas('scoring_session_claims', [
            'id' => $claimId,
            'status' => ClaimStatus::Cancelled->value,
        ]);

        // A re-claim revives the same row to pending (no unique-index trip).
        $reclaimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');
        $this->assertSame($claimId, $reclaimId);
        $this->assertSame(1, ScoringSession::find($sessionId)->claims()->count());
    }

    public function test_a_stranger_cannot_cancel_someone_elses_claim(): void
    {
        [$groupId, $sessionId] = $this->hostGroupWithGuest(User::factory()->create());

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs(User::factory()->create());
        $this->deleteJson("/api/v1/scoring/claims/{$claimId}")->assertNotFound();
    }
}
