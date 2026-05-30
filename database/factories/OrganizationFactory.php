<?php

namespace Database\Factories;

use App\Models\Organization;
use App\Support\Enums\OrganizationType;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Organization>
 */
class OrganizationFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = fake()->unique()->company();

        return [
            'type' => OrganizationType::Club,
            'name' => $name,
            'slug' => Str::slug($name).'-'.fake()->unique()->numerify('####'),
            'province' => fake()->state(),
            'city' => fake()->city(),
            'is_verified' => false,
            'is_active' => true,
        ];
    }

    public function platform(): static
    {
        return $this->state(fn (): array => [
            'type' => OrganizationType::Platform,
            'name' => 'ManahPro',
            'slug' => 'manahpro',
            'is_verified' => true,
        ]);
    }
}
