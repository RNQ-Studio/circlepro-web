<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_category_factory_persists_to_database(): void
    {
        $category = Category::factory()->create();

        $this->assertDatabaseHas('categories', [
            'id' => $category->id,
            'slug' => $category->slug,
            'is_active' => true,
        ]);
    }
}
