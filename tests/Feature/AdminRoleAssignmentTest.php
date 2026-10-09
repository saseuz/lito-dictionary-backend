<?php

use App\Actions\Admin\AssignAdminRole;
use App\Models\Admin;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Exceptions\GuardDoesNotMatch;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => 'test-password',
    ]);
    $this->role = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $this->assign = app(AssignAdminRole::class);
});

afterEach(function () {
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('the assignment action gives an Admin one admin role', function () {
    $result = $this->assign->handle($this->admin, $this->role);

    expect($result)->toBe($this->admin)
        ->and($result->hasRole('editor', 'admin'))->toBeTrue();
    $this->assertDatabaseCount('model_has_roles', 1);
    $this->assertDatabaseHas('model_has_roles', [
        'model_type' => Admin::class,
        'model_id' => $this->admin->id,
        'role_id' => $this->role->id,
    ]);
});

test('replacing or reassigning a role leaves exactly one assignment', function () {
    $replacement = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);
    $this->assign->handle($this->admin, $this->role);
    $this->assign->handle($this->admin, $replacement);
    $this->assign->handle($this->admin, $replacement);

    expect($this->admin->roles->modelKeys())->toBe([$replacement->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
    $this->assertDatabaseMissing('model_has_roles', ['role_id' => $this->role->id]);
});

test('a web role is rejected without changing the old role', function () {
    $this->assign->handle($this->admin, $this->role);
    $webRole = Role::create(['name' => 'web-editor', 'guard_name' => 'web']);

    expect(fn () => $this->assign->handle($this->admin, $webRole))->toThrow(GuardDoesNotMatch::class);
    expect($this->admin->roles->modelKeys())->toBe([$this->role->id])
        ->and($this->admin->fresh()->roles->modelKeys())->toBe([$this->role->id]);
});

test('the database rejects a second pivot assignment even when the action is bypassed', function () {
    $this->assign->handle($this->admin, $this->role);
    $other = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);

    expect(fn () => DB::table('model_has_roles')->insert([
        'model_type' => Admin::class,
        'model_id' => $this->admin->id,
        'role_id' => $other->id,
    ]))->toThrow(UniqueConstraintViolationException::class);

    $this->assertDatabaseCount('model_has_roles', 1);
});

test('role replacement updates effective permissions on the already loaded Admin', function () {
    $edit = Permission::create(['name' => 'dictionary.edit', 'guard_name' => 'admin']);
    $review = Permission::create(['name' => 'dictionary.review', 'guard_name' => 'admin']);
    $replacement = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);
    $this->role->givePermissionTo($edit);
    $replacement->givePermissionTo($review);
    $this->assign->handle($this->admin, $this->role);
    $this->admin->load('roles.permissions', 'permissions');

    expect($this->admin->can('dictionary.edit'))->toBeTrue()
        ->and($this->admin->can('dictionary.review'))->toBeFalse();

    $this->assign->handle($this->admin, $replacement);

    expect($this->admin->can('dictionary.edit'))->toBeFalse()
        ->and($this->admin->can('dictionary.review'))->toBeTrue()
        ->and($this->admin->getAllPermissions()->modelKeys())->toBe([$review->id])
        ->and($this->admin->permissions)->toBeEmpty();
});

test('a failed replacement rolls back to the original role', function () {
    $this->assign->handle($this->admin, $this->role);
    $replacement = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);
    config(['permission.events_enabled' => true]);
    Event::listen(RoleAttachedEvent::class, function (): void {
        throw new RuntimeException('Simulated assignment failure.');
    });

    expect(fn () => $this->assign->handle($this->admin, $replacement))
        ->toThrow(RuntimeException::class, 'Simulated assignment failure.');

    expect($this->admin->fresh()->roles->modelKeys())->toBe([$this->role->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
});
