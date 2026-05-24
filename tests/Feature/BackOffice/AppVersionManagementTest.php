<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\AppVersions\AppVersionResource;
use App\Models\AppVersion;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AppVersionManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_access_app_version_management(): void
    {
        $admin = $this->userWithRole('admin');
        AppVersion::factory()->create([
            'platform' => \App\Support\Enums\DevicePlatform::Android,
            'min_version' => '1.0.0',
            'latest_version' => '1.1.0',
        ]);

        $this->actingAs($admin)
            ->get(AppVersionResource::getUrl('index'))
            ->assertOk();
    }

    public function test_unauthorized_user_cannot_access_app_version_management(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(AppVersionResource::getUrl('index'))
            ->assertForbidden();
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
