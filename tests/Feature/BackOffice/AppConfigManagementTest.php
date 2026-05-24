<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\AppConfigs\AppConfigResource;
use App\Models\AppConfig;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppConfigManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_app_config_management(): void
    {
        $admin = $this->userWithRole('admin');
        AppConfig::factory()->create([
            'key' => 'site_name',
            'value' => 'Laravel Starter',
            'type' => \App\Support\Enums\AppConfigType::String,
        ]);

        $this->actingAs($admin)
            ->get(AppConfigResource::getUrl('index'))
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_access_app_config_management(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(AppConfigResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
