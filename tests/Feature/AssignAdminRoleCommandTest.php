<?php

use App\Actions\Admin\AssignAdminRole;
use App\Models\Admin;
use Illuminate\Support\Facades\Event;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    $this->admin = Admin::create([
        'name' => 'Existing Admin',
        'email' => 'admin@example.com',
        'password' => 'existing-password',
    ]);
    $this->passwordHash = $this->admin->password;
    $this->editor = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $this->reviewer = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);
    Role::create(['name' => 'web-only', 'guard_name' => 'web']);
});

afterEach(function () {
    $this->assertDatabaseCount('admins', 1);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('model_has_permissions', 0);
    expect($this->admin->fresh()->password)->toBe($this->passwordHash);
});

test('existing-account assignment selects one role without changing the password', function (bool $hasRole) {
    if ($hasRole) {
        app(AssignAdminRole::class)->handle($this->admin, $this->editor);
    }

    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', ' ADMIN@example.com ')
        ->expectsChoice('Role', 'reviewer', ['editor', 'reviewer'], true)
        ->expectsOutput('Admin: admin@example.com')
        ->expectsOutput('Current role: '.($hasRole ? 'editor' : 'none'))
        ->expectsOutput('Proposed role: reviewer')
        ->expectsConfirmation('Change this Admin role?', 'yes')
        ->expectsOutput('Admin role updated: admin@example.com (role: reviewer)')
        ->assertSuccessful();

    expect($this->admin->fresh()->roles->modelKeys())->toBe([$this->reviewer->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
})->with([true, false]);

test('missing Admin accounts fail without any changes', function () {
    app(AssignAdminRole::class)->handle($this->admin, $this->editor);

    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', 'missing@example.com')
        ->expectsOutput('No Admin exists with that email.')
        ->assertFailed();

    expect($this->admin->fresh()->roles->modelKeys())->toBe([$this->editor->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
});

test('cancelling confirmation leaves the current role unchanged', function () {
    app(AssignAdminRole::class)->handle($this->admin, $this->editor);

    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', 'admin@example.com')
        ->expectsChoice('Role', 'reviewer', ['editor', 'reviewer'], true)
        ->expectsOutput('Admin: admin@example.com')
        ->expectsOutput('Current role: editor')
        ->expectsOutput('Proposed role: reviewer')
        ->expectsConfirmation('Change this Admin role?', 'no')
        ->expectsOutput('Cancelled. No changes made.')
        ->assertSuccessful();

    expect($this->admin->fresh()->roles->modelKeys())->toBe([$this->editor->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
});

test('existing-account assignment fails clearly when no admin roles exist', function () {
    Role::where('guard_name', 'admin')->delete();

    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', 'admin@example.com')
        ->expectsOutput('No admin roles exist. Run php artisan db:seed --class=AdminRolesSeeder first.')
        ->assertFailed();

    $this->assertDatabaseCount('model_has_roles', 0);
});

test('a failed existing-account assignment preserves the original role', function () {
    app(AssignAdminRole::class)->handle($this->admin, $this->editor);
    config(['permission.events_enabled' => true]);
    Event::listen(RoleAttachedEvent::class, function (): void {
        throw new RuntimeException('Simulated assignment failure.');
    });

    $this->artisan('app:assign-admin-role')
        ->expectsQuestion('Admin email', 'admin@example.com')
        ->expectsChoice('Role', 'reviewer', ['editor', 'reviewer'], true)
        ->expectsOutput('Admin: admin@example.com')
        ->expectsOutput('Current role: editor')
        ->expectsOutput('Proposed role: reviewer')
        ->expectsConfirmation('Change this Admin role?', 'yes')
        ->expectsOutput('Unable to assign the selected role.')
        ->assertFailed();

    expect($this->admin->fresh()->roles->modelKeys())->toBe([$this->editor->id]);
    $this->assertDatabaseCount('model_has_roles', 1);
});
