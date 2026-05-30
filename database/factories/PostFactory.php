<?php

namespace Database\Factories;

use App\Models\Post;
use App\Models\User;
use App\Support\Enums\PostVisibility;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'author_id' => User::factory(),
            'body' => fake()->sentence(12),
            'visibility' => PostVisibility::Public->value,
        ];
    }
}
