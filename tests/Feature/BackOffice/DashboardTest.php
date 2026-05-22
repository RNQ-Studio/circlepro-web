<?php

namespace Tests\Feature\BackOffice;

use App\Models\Category;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_dashboard_renders_starter_overview(): void
    {
        $admin = User::factory()->create();
        $admin->assignRole('super-admin');

        User::factory()->count(2)->create();
        Category::factory()->count(3)->create();

        $this->actingAs($admin)
            ->get('/admin')
            ->assertOk()
            ->assertSee('Laravel Starter')
            ->assertSee('Users')
            ->assertSee('Roles')
            ->assertSee('Categories');
    }
}
