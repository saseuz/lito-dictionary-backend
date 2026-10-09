<?php

use Database\Seeders\AdminRolesSeeder;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

test('seeding twice creates one admin super-admin role without accounts or permissions', function () {
    Role::create(['name' => 'super-admin', 'guard_name' => 'web']);

    $this->seed(AdminRolesSeeder::class);
    $this->seed(AdminRolesSeeder::class);

    expect(Role::where('name', 'super-admin')->where('guard_name', 'admin')->count())->toBe(1);
    $this->assertDatabaseCount('roles', 2);
    $this->assertDatabaseCount('admins', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('permissions', 0);
    $this->assertDatabaseCount('role_has_permissions', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
    $this->assertDatabaseCount('model_has_permissions', 0);
});
