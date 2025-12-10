<?php

namespace Database\Seeders;

use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class RolesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        $permissions = [
            'view-dashboard',
            'manage-users',
            'manage-roles',
            'manage-projects',
            'manage-tasks',
            'view-reports',
            'run-automation',
        ];

        foreach ($permissions as $permission) {
            Permission::firstOrCreate([
                'name' => $permission,
                'guard_name' => 'web',
            ]);
        }

        $rolesWithPermissions = [
            'super-admin' => $permissions,
            'admin' => $permissions,
            'team-lead' => [
                'view-dashboard',
                'manage-projects',
                'manage-tasks',
                'view-reports',
            ],
            'developer' => [
                'view-dashboard',
                'manage-tasks',
                'view-reports',
            ],
            'automation' => [
                'view-dashboard',
                'run-automation',
                'view-reports',
            ],
        ];

        foreach ($rolesWithPermissions as $role => $rolePermissions) {
            $roleModel = Role::firstOrCreate([
                'name' => $role,
                'guard_name' => 'web',
            ]);

            $roleModel->syncPermissions($rolePermissions);
        }
    }
}
