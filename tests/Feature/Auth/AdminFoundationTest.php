<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

beforeEach(function () {
    // Use a new in-memory database without running migrate:fresh or rollback.
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
    ]);
    DB::purge('sqlite');

    $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])
        ->assertSuccessful();
});

test('admins are created with UUIDs', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);

    expect(Str::isUuid($admin->id))->toBeTrue();
    $this->assertDatabaseHas('admins', ['id' => $admin->id]);
});

test('admin passwords are hashed', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'plain-text-password',
    ])->fresh();

    expect($admin->password)->not->toBe('plain-text-password');
    expect(Hash::check('plain-text-password', $admin->password))->toBeTrue();
});

test('admin secrets are hidden from serialization', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $admin->setRememberToken('secret-remember-token');
    $admin->save();
    $admin->refresh();

    expect($admin->getRememberToken())->toBe('secret-remember-token');
    expect($admin->toArray())->toHaveKey('email')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
    expect(json_decode($admin->toJson(), true))->toHaveKey('email')
        ->not->toHaveKey('password')
        ->not->toHaveKey('remember_token');
});

test('the admin guard provider retrieves Admin accounts', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $provider = Auth::guard('admin')->getProvider();

    expect($provider->retrieveById($admin->id))->toBeInstanceOf(Admin::class)
        ->id->toBe($admin->id);
    expect($provider->retrieveByCredentials(['email' => $admin->email]))
        ->toBeInstanceOf(Admin::class)
        ->id->toBe($admin->id);
});

test('admin-only accounts cannot authenticate through the web guard', function () {
    $admin = Admin::create([
        'name' => 'Admin',
        'email' => 'admin@example.com',
        'password' => 'password',
    ]);
    $credentials = ['email' => $admin->email, 'password' => 'password'];

    expect(Auth::guard('admin')->validate($credentials))->toBeTrue();
    expect(Auth::guard('web')->attempt($credentials))->toBeFalse();
    $this->assertGuest('web');
});

test('user-only accounts cannot authenticate through the admin guard', function () {
    $user = User::factory()->create(['password' => 'password']);
    $credentials = ['email' => $user->email, 'password' => 'password'];

    expect(Auth::guard('web')->validate($credentials))->toBeTrue();
    expect(Auth::guard('admin')->attempt($credentials))->toBeFalse();
    $this->assertGuest('admin');
});
