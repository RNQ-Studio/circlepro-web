<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Categories\CategoryResource;
use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CategoryManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_category_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $category = Category::factory()->create();

        $this->actingAs($admin)
            ->get(CategoryResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(CategoryResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(CategoryResource::getUrl('edit', ['record' => $category]))
            ->assertOk();
    }

    public function test_user_without_category_permission_cannot_access_category_management(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(CategoryResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
