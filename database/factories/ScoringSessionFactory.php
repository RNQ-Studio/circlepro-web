<?php

namespace Database\Factories;

use App\Models\ScoringSession;
use App\Models\User;
use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ParticipationStatus;
use App\Support\Enums\ScoringSessionStatus;
use App\Support\Enums\SyncSource;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ScoringSession>
 */
class ScoringSessionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $numEnds = fake()->randomElement([6, 10, 12]);
        $arrowsPerEnd = fake()->randomElement([3, 6]);

        return [
            'user_id' => User::factory(),
            'bow_class' => fake()->randomElement(BowClass::cases()),
            'distance_category' => fake()->randomElement(DistanceCategory::cases()),
            'distance_m' => fake()->randomElement([18, 30, 50, 70]),
            'environment' => fake()->randomElement(ArcheryEnvironment::cases()),
            'target_face_cm' => fake()->randomElement([40, 80, 122]),
            'num_ends' => $numEnds,
            'arrows_per_end' => $arrowsPerEnd,
            'status' => ScoringSessionStatus::InProgress,
            'source' => SyncSource::Mobile,
            'client_uuid' => fake()->uuid(),
            'started_at' => now(),
        ];
    }

    public function completed(): static
    {
        return $this->state(fn (): array => [
            'status' => ScoringSessionStatus::Completed,
            'completed_at' => now(),
        ]);
    }

    /**
     * A guest participant row in a group: no owner yet, just a display name.
     * Excluded from stats/PB because every stats query filters on user_id.
     */
    public function guest(): static
    {
        return $this->state(fn (): array => [
            'user_id' => null,
            'guest_name' => fake()->firstName(),
            'added_by_user_id' => User::factory(),
            'participation_status' => ParticipationStatus::HostAdded,
        ]);
    }
}
