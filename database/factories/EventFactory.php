<?php

namespace Database\Factories;

use App\Models\Event;
use App\Models\Organization;
use App\Models\User;
use App\Support\Enums\EventFormat;
use App\Support\Enums\EventStatus;
use App\Support\Enums\EventTier;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Event>
 */
class EventFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = fake()->unique()->sentence(4);

        return [
            'organization_id' => Organization::factory(),
            'created_by' => User::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.fake()->unique()->numerify('####'),
            'description' => fake()->paragraph(),
            'tier' => fake()->randomElement(EventTier::cases()),
            'format' => fake()->randomElement(EventFormat::cases()),
            'status' => EventStatus::Draft,
            'province' => fake()->state(),
            'city' => fake()->city(),
            'venue_name' => fake()->company(),
            'starts_at' => now()->addDays(5),
            'ends_at' => now()->addDays(7),
        ];
    }
}
