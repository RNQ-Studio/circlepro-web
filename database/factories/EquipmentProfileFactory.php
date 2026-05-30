<?php

namespace Database\Factories;

use App\Models\EquipmentProfile;
use App\Models\User;
use App\Support\Enums\BowClass;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EquipmentProfile>
 */
class EquipmentProfileFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['Recurve Latihan', 'Compound Lomba', 'Barebow Harian']),
            'bow_class' => fake()->randomElement(BowClass::cases()),
            'bow_model' => fake()->optional()->words(2, true),
            'draw_weight_lbs' => fake()->optional()->randomFloat(1, 18, 50),
            'arrow_spec' => fake()->optional()->bothify('Easton ###'),
            'is_default' => false,
        ];
    }

    public function default(): static
    {
        return $this->state(fn (): array => ['is_default' => true]);
    }
}
