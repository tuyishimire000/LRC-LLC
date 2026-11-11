<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        $roles = [
            ['name' => 'super_admin', 'label' => 'Super administrator'],
            ['name' => 'property_manager', 'label' => 'Property manager'],
            ['name' => 'finance_officer', 'label' => 'Finance officer'],
            ['name' => 'maintenance_staff', 'label' => 'Maintenance staff'],
            ['name' => 'owner', 'label' => 'Property owner'],
            ['name' => 'tenant', 'label' => 'Tenant'],
        ];

        foreach ($roles as $role) {
            DB::table('roles')->updateOrInsert(
                ['name' => $role['name']],
                [...$role, 'created_at' => $now, 'updated_at' => $now]
            );
        }

        $permissions = [
            ['name' => 'properties.view', 'label' => 'View properties'],
            ['name' => 'properties.manage', 'label' => 'Manage properties'],
            ['name' => 'owners.manage', 'label' => 'Manage owners'],
            ['name' => 'leases.manage', 'label' => 'Manage leases'],
            ['name' => 'payments.verify', 'label' => 'Verify tenant payments'],
            ['name' => 'finance.view', 'label' => 'View finance records'],
            ['name' => 'payouts.approve', 'label' => 'Approve owner payouts'],
            ['name' => 'users.manage', 'label' => 'Manage users'],
        ];

        foreach ($permissions as $permission) {
            DB::table('permissions')->updateOrInsert(
                ['name' => $permission['name']],
                [...$permission, 'created_at' => $now, 'updated_at' => $now]
            );
        }
    }
}
