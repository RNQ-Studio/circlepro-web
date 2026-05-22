<?php

namespace Tests\Feature\BackOffice;

use App\Filament\Resources\Roles\RoleResource;
use App\Filament\Resources\Users\UserResource;
use App\Models\User;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class UserRoleManagementTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->seed(RolePermissionSeeder::class);
    }

    public function test_admin_can_open_user_management_pages(): void
    {
        $admin = $this->userWithRole('admin');
        $managedUser = $this->userWithRole('staff');

        $this->actingAs($admin)
            ->get(UserResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(UserResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($admin)
            ->get(UserResource::getUrl('edit', ['record' => $managedUser]))
            ->assertOk();
    }

    public function test_role_assignment_changes_user_authorization(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($user->can('viewAny', User::class));

        $user->assignRole('admin');

        $this->assertTrue($user->can('viewAny', User::class));
        $this->assertTrue($user->can('create', User::class));
        $this->assertFalse($user->can('create', Role::class));
    }

    public function test_staff_cannot_access_user_or_role_management_pages(): void
    {
        $staff = $this->userWithRole('staff');

        $this->actingAs($staff)
            ->get(UserResource::getUrl('index'))
            ->assertForbidden();

        $this->actingAs($staff)
            ->get(RoleResource::getUrl('index'))
            ->assertForbidden();
    }

    public function test_super_admin_can_open_role_management_pages(): void
    {
        $superAdmin = $this->userWithRole('super-admin');
        $role = Role::query()->where('name', 'staff')->firstOrFail();

        $this->actingAs($superAdmin)
            ->get(RoleResource::getUrl('index'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(RoleResource::getUrl('create'))
            ->assertOk();

        $this->actingAs($superAdmin)
            ->get(RoleResource::getUrl('edit', ['record' => $role]))
            ->assertOk();
    }

    public function test_role_policy_controls_permission_assignment(): void
    {
        $role = Role::create(['name' => 'limited-manager', 'guard_name' => 'web']);
        $role->givePermissionTo('users.viewAny');

        $this->assertTrue($role->hasPermissionTo('users.viewAny'));
        $this->assertFalse($role->hasPermissionTo('users.create'));

        $role->syncPermissions(Permission::query()->pluck('name'));

        $this->assertTrue($role->hasPermissionTo('users.create'));
        $this->assertTrue($role->hasPermissionTo('roles.delete'));
    }

    private function userWithRole(string $role): User
    {
        $user = User::factory()->create();
        $user->assignRole($role);

        return $user;
    }
}
