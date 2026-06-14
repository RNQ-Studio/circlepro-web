<?php

namespace Tests\Feature;

use App\Models\ScoringSession;
use App\Models\ScoringSessionClaim;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ClaimStatus;
use App\Support\Enums\ParticipationStatus;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Sprint 01 — data foundation for Latihan Bersama (group scoring).
 * Verifies guest support, claim queue, relations and — critically — that
 * guest rows (user_id NULL) stay invisible to user-scoped queries (§3.2).
 */
class GroupScoringFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_group_and_claim_factories_create_records(): void
    {
        $group = ScoringSessionGroup::factory()->create();
        $this->assertDatabaseHas('scoring_session_groups', ['id' => $group->id]);

        $claim = ScoringSessionClaim::factory()->create();
        $this->assertDatabaseHas('scoring_session_claims', ['id' => $claim->id]);
        $this->assertSame(ClaimStatus::Pending, $claim->status);
    }

    public function test_guest_session_has_no_owner_and_reports_is_guest(): void
    {
        $group = ScoringSessionGroup::factory()->create();

        $guest = ScoringSession::factory()->guest()->create([
            'scoring_session_group_id' => $group->id,
        ]);

        $this->assertNull($guest->user_id);
        $this->assertNotNull($guest->guest_name);
        $this->assertTrue($guest->isGuest());
        $this->assertSame(ParticipationStatus::HostAdded, $guest->participation_status);

        $owned = ScoringSession::factory()->create();
        $this->assertFalse($owned->isGuest());
    }

    public function test_group_relations_resolve(): void
    {
        $group = ScoringSessionGroup::factory()->create();
        ScoringSession::factory()->guest()->create(['scoring_session_group_id' => $group->id]);
        $claim = ScoringSessionClaim::factory()->create(['scoring_session_group_id' => $group->id]);

        $this->assertCount(1, $group->participants);
        $this->assertCount(1, $group->sessions);
        $this->assertTrue($group->claims->contains($claim));
    }

    public function test_claim_relations_resolve(): void
    {
        $claim = ScoringSessionClaim::factory()->create();

        $this->assertInstanceOf(ScoringSession::class, $claim->session);
        $this->assertInstanceOf(ScoringSessionGroup::class, $claim->group);
        $this->assertInstanceOf(User::class, $claim->claimant);
        $this->assertNull($claim->resolvedBy);
    }

    public function test_guest_sessions_are_excluded_from_user_scoped_queries(): void
    {
        $user = User::factory()->create();
        $group = ScoringSessionGroup::factory()->create();

        ScoringSession::factory()->for($user)->create(['scoring_session_group_id' => $group->id]);
        ScoringSession::factory()->guest()->count(2)->create(['scoring_session_group_id' => $group->id]);

        // The user owns exactly one row; the two guests must not leak in.
        $this->assertSame(1, ScoringSession::forUser($user->id)->count());
        $this->assertSame(3, $group->participants()->count());
    }

    public function test_a_slot_cannot_be_claimed_twice_by_the_same_user(): void
    {
        $claim = ScoringSessionClaim::factory()->create();

        $this->expectException(QueryException::class);

        ScoringSessionClaim::factory()->create([
            'scoring_session_id' => $claim->scoring_session_id,
            'scoring_session_group_id' => $claim->scoring_session_group_id,
            'claimant_user_id' => $claim->claimant_user_id,
        ]);
    }
}
