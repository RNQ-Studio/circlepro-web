<?php

namespace Database\Factories;

use App\Models\ScoringSession;
use App\Models\ScoringSessionClaim;
use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ClaimStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringSessionClaim>
 */
class ScoringSessionClaimFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        // Keep the claim coherent: the claimed session is a guest slot that
        // belongs to the same group the claim points at.
        $group = ScoringSessionGroup::factory()->create();
        $session = ScoringSession::factory()->guest()->create([
            'scoring_session_group_id' => $group->id,
        ]);

        return [
            'scoring_session_id' => $session->id,
            'scoring_session_group_id' => $group->id,
            'claimant_user_id' => User::factory(),
            'status' => ClaimStatus::Pending,
            'message' => fake()->optional()->sentence(),
        ];
    }

    public function approved(): static
    {
        return $this->state(fn (): array => [
            'status' => ClaimStatus::Approved,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }

    public function rejected(): static
    {
        return $this->state(fn (): array => [
            'status' => ClaimStatus::Rejected,
            'resolved_by_user_id' => User::factory(),
            'resolved_at' => now(),
        ]);
    }
}
