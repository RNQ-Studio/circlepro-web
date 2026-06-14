<?php

namespace Database\Factories;

use App\Models\ScoringSessionGroup;
use App\Models\User;
use App\Support\Enums\ArcheryEnvironment;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\ScoringSessionStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ScoringSessionGroup>
 */
class ScoringSessionGroupFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'host_user_id' => User::factory(),
            'title' => fake()->randomElement(['Latihan Sore', 'Friendly Klub', 'Sparring Recurve']),
            'distance_category' => fake()->randomElement(DistanceCategory::cases()),
            'distance_m' => fake()->randomElement([18, 30, 50, 70]),
            'environment' => fake()->randomElement(ArcheryEnvironment::cases()),
            'target_face_cm' => fake()->randomElement([40, 80, 122]),
            'num_ends' => fake()->randomElement([6, 10, 12]),
            'arrows_per_end' => fake()->randomElement([3, 6]),
            'join_code' => Str::upper(Str::random(8)),
            'status' => ScoringSessionStatus::InProgress,
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
}
