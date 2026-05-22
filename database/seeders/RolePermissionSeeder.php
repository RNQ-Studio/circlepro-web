<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolePermissionSeeder extends Seeder
{
    /**
     * Roles & permissions live on the `web` guard. Both the `web` (session)
     * and `api` (Passport) guards share the `users` provider, so permission
     * checks resolve correctly in both back-office and API contexts.
     */
    private const GUARD = 'web';

    /** @var list<string> */
    private const RESOURCES = ['users', 'roles', 'categories'];

    /** @var list<string> */
    private const ABILITIES = ['viewAny', 'view', 'create', 'update', 'delete'];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::RESOURCES as $resource) {
            foreach (self::ABILITIES as $ability) {
                Permission::findOrCreate("{$resource}.{$ability}", self::GUARD);
            }
        }

        $superAdmin = Role::findOrCreate('super-admin', self::GUARD);
        $admin = Role::findOrCreate('admin', self::GUARD);
        $staff = Role::findOrCreate('staff', self::GUARD);

        // super-admin also bypasses checks via Gate::before; permissions are
        // assigned explicitly so the back-office UI reflects full access.
        $superAdmin->syncPermissions(Permission::all());

        $admin->syncPermissions([
            'users.viewAny', 'users.view', 'users.create', 'users.update', 'users.delete',
            'roles.viewAny', 'roles.view',
            'categories.viewAny', 'categories.view', 'categories.create', 'categories.update', 'categories.delete',
        ]);

        $staff->syncPermissions([
            'categories.viewAny', 'categories.view', 'categories.create', 'categories.update',
        ]);
    }
}
