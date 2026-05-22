<?php

namespace Tests\Feature\Api;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Passport\Passport;
use Tests\TestCase;

class CategoryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_guest_cannot_access_categories(): void
    {
        $this->getJson('/api/v1/categories')
            ->assertUnauthorized()
            ->assertJson(['code' => 'UNAUTHENTICATED']);
    }

    public function test_admin_can_list_categories_with_filter_sort_and_pagination(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        Category::factory()->create(['name' => 'Beta Category', 'slug' => 'beta-category', 'is_active' => false]);
        Category::factory()->create(['name' => 'Alpha Category', 'slug' => 'alpha-category', 'is_active' => true]);

        $this->getJson('/api/v1/categories?filter[is_active]=1&sort=-name&per_page=1')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Alpha Category')
            ->assertJsonPath('meta.pagination.per_page', 1)
            ->assertJsonPath('meta.pagination.total', 1);
    }

    public function test_admin_can_create_show_update_and_delete_category(): void
    {
        Passport::actingAs($this->userWithRole('admin'));

        $categoryId = $this->postJson('/api/v1/categories', [
            'name' => 'Office Supplies',
            'description' => 'Reusable master data.',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'Office Supplies')
            ->assertJsonPath('data.slug', 'office-supplies')
            ->json('data.id');

        $this->getJson("/api/v1/categories/{$categoryId}")
            ->assertOk()
            ->assertJsonPath('data.name', 'Office Supplies');

        $this->putJson("/api/v1/categories/{$categoryId}", [
            'name' => 'Office Equipment',
            'is_active' => false,
        ])
            ->assertOk()
            ->assertJsonPath('data.slug', 'office-equipment')
            ->assertJsonPath('data.is_active', false);

        $this->deleteJson("/api/v1/categories/{$categoryId}")
            ->assertOk()
            ->assertJson(['message' => 'Category deleted']);

        $this->assertSoftDeleted('categories', ['id' => $categoryId]);
    }

    public function test_staff_cannot_delete_category(): void
    {
        Passport::actingAs($this->userWithRole('staff'));
        $category = Category::factory()->create();

        $this->deleteJson("/api/v1/categories/{$category->getKey()}")
            ->assertForbidden();

        $this->assertNotSoftDeleted('categories', ['id' => $category->getKey()]);
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
