<?php

namespace Tests\Feature\Api;

use App\Models\Notification;
use App\Models\ScoringSession;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ClaimStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

/**
 * Sprint 15 — Tanpa Gating Sosial & Onboarding Skill Klaim (Phase 2).
 *
 * Locks O1/K5 in code: a Latihan Bersama (social) session is NEVER hard-blocked
 * by the Free weekly quota, and — per the owner's decision (flavor a) — it never
 * even *counts* toward that quota (15.1). Claiming a slot is always allowed,
 * quota or not (15.2). And there is no hidden path that still blocks social
 * scoring (15.5). Finally, approval rides the new owner's headline numbers along
 * so the mobile success screen can welcome them as an archer, not just move a row
 * (15.3 backend half).
 */
class SocialScoringNoGatingTest extends TestCase
{
    use RefreshDatabase;

    /** A free user's individual-session payload (the quota-governed path). */
    private function individualSessionPayload(string $title = 'Latihan Pribadi'): array
    {
        return [
            'title' => $title,
            'bow_class' => 'recurve',
            'distance_category' => '30m',
            'distance_m' => 18,
            'num_ends' => 6,
            'arrows_per_end' => 3,
            'status' => 'completed',
            'started_at' => now()->toIso8601String(),
        ];
    }

    /**
     * Host a 50m group (1 end · 3 arrows) and quick-add one guest; returns
     * [groupId, guestSessionId]. Mirrors GroupClaimTest so the claim/approve
     * flow is exercised exactly like Sprint 13/14.
     *
     * @return array{0: string, 1: string}
     */
    private function hostGroupWithGuest(User $host): array
    {
        Passport::actingAs($host);

        $groupId = $this->postJson('/api/v1/scoring/groups', [
            'title' => 'Sesi Sore Klub Rajawali',
            'distance_category' => '50m',
            'distance_m' => 50,
            'environment' => 'outdoor',
            'target_face_cm' => 122,
            'num_ends' => 1,
            'arrows_per_end' => 3,
        ])->assertCreated()->json('data.id');

        // A bow class on the slot lets the PB be born on approval (the success
        // screen's "PB pertamamu" moment, 15.3).
        $sessionId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants", [
            'participants' => [['name' => 'Pak Budi', 'bow_class' => 'recurve']],
        ])->assertCreated()->json('data.0.id');

        return [$groupId, $sessionId];
    }

    /** Score a guest slot to completion (host acting): 3×9 = 27. */
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

    // --- 15.1 — social sessions excluded from the quota --------------------

    public function test_social_sessions_are_excluded_from_the_free_weekly_quota(): void
    {
        $andi = User::factory()->create();
        $group = ScoringSessionGroup::factory()->create();

        // Three Latihan Bersama sessions owned by Andi this week.
        ScoringSession::factory()->count(3)->create([
            'user_id' => $andi->id,
            'scoring_session_group_id' => $group->id,
            'started_at' => now(),
        ]);

        Passport::actingAs($andi);

        // The usage counter mirrors the gate: social sessions are invisible.
        $this->getJson('/api/v1/monetization/subscription')
            ->assertOk()
            ->assertJsonPath('data.usage.scoring_sessions_this_week', 0)
            ->assertJsonPath('data.usage.is_gated', false);

        // And an individual session is still allowed despite 3 social sessions.
        $this->postJson('/api/v1/scoring/sessions', $this->individualSessionPayload())
            ->assertCreated();
    }

    public function test_personal_sessions_still_count_and_still_gate(): void
    {
        $andi = User::factory()->create();

        // Three personal (non-group) sessions this week → at the Free limit.
        ScoringSession::factory()->count(3)->create([
            'user_id' => $andi->id,
            'scoring_session_group_id' => null,
            'started_at' => now(),
        ]);

        Passport::actingAs($andi);

        $this->getJson('/api/v1/monetization/subscription')
            ->assertOk()
            ->assertJsonPath('data.usage.scoring_sessions_this_week', 3)
            ->assertJsonPath('data.usage.is_gated', true);

        // The 4th individual session is gated — the quota itself still works.
        $this->postJson('/api/v1/scoring/sessions', $this->individualSessionPayload('Gated'))
            ->assertStatus(402);
    }

    // --- 15.5 — no hidden path that still blocks social scoring ------------

    public function test_a_free_user_at_quota_can_still_join_and_score_socially(): void
    {
        // A host opens a live group.
        $host = User::factory()->create();
        $group = ScoringSessionGroup::factory()->for($host, 'host')->create([
            'distance_category' => '50m',
            'distance_m' => 50,
            'num_ends' => 1,
            'arrows_per_end' => 3,
            'target_face_cm' => 122,
        ]);

        // Andi is already maxed out on personal sessions this week.
        $andi = User::factory()->create();
        ScoringSession::factory()->count(3)->create([
            'user_id' => $andi->id,
            'scoring_session_group_id' => null,
            'started_at' => now(),
        ]);

        Passport::actingAs($andi);

        // The individual path is gated (sanity), but the social path is not.
        $this->postJson('/api/v1/scoring/sessions', $this->individualSessionPayload('Gated'))
            ->assertStatus(402);

        $sessionId = $this->postJson("/api/v1/scoring/groups/{$group->id}/join", [
            'bow_class' => 'recurve',
        ])->assertCreated()->json('data.id');

        $this->putJson("/api/v1/scoring/groups/{$group->id}/participants/{$sessionId}/score", [
            'status' => 'completed',
            'ends' => [[
                'end_number' => 1,
                'arrows' => [
                    ['arrow_index' => 0, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 1, 'score_value' => 10, 'is_x' => false, 'is_miss' => false],
                    ['arrow_index' => 2, 'score_value' => 9, 'is_x' => false, 'is_miss' => false],
                ],
            ]],
        ])->assertOk();
    }

    // --- 15.2 — claiming is always allowed --------------------------------

    public function test_claim_and_approve_are_never_gated_by_the_quota(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);
        $this->scoreSlotCompleted($groupId, $sessionId);

        // Budi is at his personal quota — claiming must still go through.
        $budi = User::factory()->create();
        ScoringSession::factory()->count(3)->create([
            'user_id' => $budi->id,
            'scoring_session_group_id' => null,
            'started_at' => now(),
        ]);

        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim", [
            'message' => 'Ini aku, Budi',
        ])->assertCreated()->json('data.id');

        // Host approves — ownership transfer is never gated either.
        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])
            ->assertOk()
            ->assertJsonPath('data.status', ClaimStatus::Approved->value);
    }

    // --- 15.3 (backend) — approval rides skill-onboarding numbers ----------

    public function test_approved_claim_notification_carries_skill_onboarding_summary(): void
    {
        $host = User::factory()->create();
        [$groupId, $sessionId] = $this->hostGroupWithGuest($host);
        $this->scoreSlotCompleted($groupId, $sessionId);

        $budi = User::factory()->create();
        Passport::actingAs($budi);
        $claimId = $this->postJson("/api/v1/scoring/groups/{$groupId}/participants/{$sessionId}/claim")
            ->assertCreated()->json('data.id');

        Passport::actingAs($host);
        $this->patchJson("/api/v1/scoring/claims/{$claimId}", ['action' => 'approve'])->assertOk();

        $note = Notification::query()
            ->where('user_id', $budi->id)
            ->where('type', 'group_claim_approved')
            ->firstOrFail();

        $this->assertSame(27, $note->data['total_score']);
        $this->assertSame(3, $note->data['arrows_shot']);
        $this->assertEqualsWithDelta(9.0, (float) $note->data['avg_per_arrow'], 0.001);
        $this->assertTrue($note->data['is_personal_best']);
        $this->assertSame('Sesi Sore Klub Rajawali', $note->data['group_title']);
    }
}
