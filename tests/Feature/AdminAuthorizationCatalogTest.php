<?php

use App\Models\Permission;
use Database\Seeders\AdminAuthorizationSeeder;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

test('the authorization catalog is idempotent and preserves role selections', function () {
    $this->seed(AdminAuthorizationSeeder::class);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $role->givePermissionTo('admins.view');
    $this->seed(AdminAuthorizationSeeder::class);
    $this->assertDatabaseCount('permission_groups', 4);
    $this->assertDatabaseCount('permissions', 12);
    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('admins', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
    expect($role->fresh()->permissions->pluck('name')->all())->toBe(['admins.view']);
    expect(Permission::whereNotNull('permission_group_id')->count())->toBe(12);
});
