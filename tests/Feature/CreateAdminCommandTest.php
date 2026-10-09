<?php

use App\Models\Admin;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Spatie\Permission\Events\RoleAttachedEvent;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    Role::create(['name' => 'web-only', 'guard_name' => 'web']);
});

afterEach(function () {
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('the command creates only an Admin with a UUID and hashed password', function () {
    $password = 'a-long-test-password';

    $this->artisan('app:create-admin')
        ->expectsChoice('Role', 'editor', ['editor'], true)
        ->expectsQuestion('Name', 'Test Admin')
        ->expectsQuestion('Email', ' ADMIN@example.com ')
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $password)
        ->expectsOutput('Admin created: admin@example.com (role: editor)')
        ->doesntExpectOutputToContain($password)
        ->doesntExpectOutputToContain('Test Admin')
        ->assertSuccessful();

    $admin = Admin::sole();
    expect($admin->roles)->toHaveCount(1)
        ->and($admin->roles->first()->name)->toBe('editor')
        ->and($admin->roles->first()->guard_name)->toBe('admin');
    $this->assertDatabaseCount('model_has_roles', 1);

    expect($admin->name)->toBe('Test Admin')
        ->and($admin->email)->toBe('admin@example.com')
        ->and(Str::isUuid($admin->id))->toBeTrue()
        ->and($admin->password)->not->toBe($password)
        ->and(Hash::check($password, $admin->password))->toBeTrue();
    $this->assertDatabaseCount('users', 0);
});

test('the command rejects duplicate admin emails without changing existing accounts', function () {
    $existing = Admin::create([
        'name' => 'Existing Admin',
        'email' => 'admin@example.com',
        'password' => 'existing-password',
    ]);

    $this->artisan('app:create-admin')
        ->expectsChoice('Role', 'editor', ['editor'], true)
        ->expectsQuestion('Name', 'Another Admin')
        ->expectsQuestion('Email', 'ADMIN@example.com')
        ->expectsQuestion('Password', 'another-long-password')
        ->expectsQuestion('Confirm password', 'another-long-password')
        ->expectsOutput('The email has already been taken.')
        ->doesntExpectOutputToContain('another-long-password')
        ->doesntExpectOutputToContain('Admin created:')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 1);
    $this->assertDatabaseCount('users', 0);
    expect($existing->fresh()->name)->toBe('Existing Admin')
        ->and(Hash::check('existing-password', $existing->fresh()->password))->toBeTrue();
});

test('invalid input is rejected before any account is created', function (string $name, string $email, string $password, string $confirmation, string $error) {
    $this->artisan('app:create-admin')
        ->expectsChoice('Role', 'editor', ['editor'], true)
        ->expectsQuestion('Name', $name)
        ->expectsQuestion('Email', $email)
        ->expectsQuestion('Password', $password)
        ->expectsQuestion('Confirm password', $confirmation)
        ->expectsOutput($error)
        ->doesntExpectOutputToContain($password)
        ->doesntExpectOutputToContain($confirmation)
        ->doesntExpectOutputToContain('Admin created:')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 0);
    $this->assertDatabaseCount('users', 0);
})->with([
    'missing name' => ['', 'admin@example.com', 'long-test-password', 'long-test-password', 'The name field is required.'],
    'invalid email' => ['Admin', 'not-an-email', 'long-test-password', 'long-test-password', 'The email field must be a valid email address.'],
    'short password' => ['Admin', 'admin@example.com', 'short', 'short', 'The password field must be at least 12 characters.'],
    'mismatched confirmation' => ['Admin', 'admin@example.com', 'long-test-password', 'different-password', 'The password field confirmation does not match.'],
]);

test('creation fails before collecting details when only web roles exist', function () {
    Role::where('guard_name', 'admin')->delete();

    $this->artisan('app:create-admin')
        ->expectsOutput('No admin roles exist. Run php artisan db:seed --class=AdminRolesSeeder first.')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
});

test('creation requires an explicit interactive role selection', function () {
    $this->artisan('app:create-admin', ['--no-interaction' => true])
        ->expectsOutput('An explicit interactive role selection is required.')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 0);
});

test('failed role assignment rolls back both the new Admin and its role assignment', function () {
    config(['permission.events_enabled' => true]);
    Event::listen(RoleAttachedEvent::class, function (): void {
        throw new RuntimeException('Simulated assignment failure.');
    });

    $this->artisan('app:create-admin')
        ->expectsChoice('Role', 'editor', ['editor'], true)
        ->expectsQuestion('Name', 'Test Admin')
        ->expectsQuestion('Email', 'admin@example.com')
        ->expectsQuestion('Password', 'long-test-password')
        ->expectsQuestion('Confirm password', 'long-test-password')
        ->expectsOutput('Unable to create Admin with the selected role. No account was created.')
        ->doesntExpectOutputToContain('long-test-password')
        ->assertFailed();

    $this->assertDatabaseCount('admins', 0);
    $this->assertDatabaseCount('users', 0);
    $this->assertDatabaseCount('model_has_roles', 0);
});
