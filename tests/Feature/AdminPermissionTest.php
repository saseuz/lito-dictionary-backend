<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Spatie\Permission\Traits\HasRoles;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    app(PermissionRegistrar::class)->forgetCachedPermissions();

    $this->admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => 'test-password',
    ]);
});

test('permission schema uses UUID model keys while preserving integer role and permission keys', function () {
    expect(config('permission.teams'))->toBeFalse()
        ->and(class_uses_recursive(User::class))->not->toContain(HasRoles::class);

    foreach (['model_has_roles' => 'role_id', 'model_has_permissions' => 'permission_id'] as $table => $key) {
        expect(Schema::getColumnType($table, 'model_id'))->toBeIn(['varchar', 'text'])
            ->and(Schema::hasIndex($table, $table.'_model_id_model_type_index'))->toBeTrue()
            ->and(Schema::hasIndex($table, [$key, 'model_id', 'model_type'], 'primary'))->toBeTrue()
            ->and(Schema::hasColumn($table, 'team_id'))->toBeFalse();
    }

    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $permission = Permission::create(['name' => 'dictionary.edit', 'guard_name' => 'admin']);
    expect($role->id)->toBeInt()->and($permission->id)->toBeInt();
});

test('an admin role can be assigned and its pivot stores the Admin UUID', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);

    $this->admin->assignRole($role);

    expect($this->admin->fresh()->hasRole('editor', 'admin'))->toBeTrue();
    $this->assertDatabaseHas('model_has_roles', [
        'role_id' => $role->id,
        'model_id' => $this->admin->id,
        'model_type' => Admin::class,
    ]);
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('Admins inherit role permissions through both Spatie and Laravel authorization', function () {
    $permission = Permission::create(['name' => 'dictionary.edit', 'guard_name' => 'admin']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $role->givePermissionTo($permission);
    $this->admin->assignRole($role);

    // The default authentication guard remains web; Admin explicitly selects admin for Spatie.
    expect(config('auth.defaults.guard'))->toBe('web')
        ->and($this->admin->hasPermissionTo('dictionary.edit'))->toBeTrue()
        ->and($this->admin->can('dictionary.edit'))->toBeTrue()
        ->and($this->admin->can('dictionary.delete'))->toBeFalse();
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('a web guard role is rejected without an assignment', function () {
    $role = Role::create(['name' => 'editor', 'guard_name' => 'web']);

    expect(fn () => $this->admin->assignRole($role))->toThrow(GuardDoesNotMatch::class);

    $this->assertDatabaseCount('model_has_roles', 0);
});

test('revoking a role permission invalidates authorization and loaded relations can be refreshed', function () {
    $permission = Permission::create(['name' => 'dictionary.edit', 'guard_name' => 'admin']);
    $role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $role->givePermissionTo($permission);
    $this->admin->assignRole($role);

    // Warm the package cache and retain separate Eloquent relationship snapshots.
    $this->admin->load('roles.permissions', 'permissions');
    $permission->load('roles');
    expect($this->admin->can('dictionary.edit'))->toBeTrue()
        ->and($this->admin->hasPermissionTo($permission))->toBeTrue();

    $role->revokePermissionTo($permission);

    // The package invalidates its permission cache, so checks by name immediately change.
    expect($this->admin->can('dictionary.edit'))->toBeFalse()
        ->and($this->admin->hasPermissionTo('dictionary.edit'))->toBeFalse();

    // Independent loaded model instances are snapshots, not automatically synchronized.
    expect($permission->roles)->toHaveCount(1)
        ->and($this->admin->roles->first()->permissions)->toHaveCount(1);

    $permission->refresh();
    $this->admin->refresh();
    expect($this->admin->hasPermissionTo($permission))->toBeFalse()
        ->and($this->admin->getAllPermissions())->toBeEmpty()
        ->and($this->admin->fresh()->can('dictionary.edit'))->toBeFalse();
    $this->assertDatabaseCount('role_has_permissions', 0);
    $this->assertDatabaseCount('model_has_permissions', 0);
});
