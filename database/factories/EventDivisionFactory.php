<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\EventDivision;
use App\Support\Enums\AgeGroup;
use App\Support\Enums\BowClass;
use App\Support\Enums\DistanceCategory;
use App\Support\Enums\Gender;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EventDivision>
 */
class EventDivisionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'event_id' => Event::factory(),
            'bow_class' => fake()->randomElement(BowClass::cases()),
            'gender' => fake()->randomElement(Gender::cases()),
            'age_group' => fake()->randomElement(AgeGroup::cases()),
            'distance_category' => fake()->randomElement(DistanceCategory::cases()),
            'distance_m' => fake()->randomElement([30, 50, 70]),
            'num_arrows' => 72,
            'max_score' => 720,
            'entry_fee' => fake()->randomElement([50000, 100000, 150000]),
            'capacity' => 32,
            'num_participants' => 0,
        ];
    }
}
