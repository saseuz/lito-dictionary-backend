<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\PermissionGroup;
use App\Support\AdminPermissionCatalog;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\PermissionRegistrar;

class AdminAuthorizationSeeder extends Seeder
{
    public function run(): void
    {
        DB::transaction(function (): void {
            $this->call(AdminRolesSeeder::class);
            foreach (AdminPermissionCatalog::GROUPS as $key => $definition) {
                $group = PermissionGroup::updateOrCreate(['key' => $key, 'guard_name' => 'admin'], ['name' => $definition['name']]);
                foreach ($definition['permissions'] as $name) {
                    Permission::updateOrCreate(['name' => $name, 'guard_name' => 'admin'], ['permission_group_id' => $group->id]);
                }
            }
        });
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }
}
