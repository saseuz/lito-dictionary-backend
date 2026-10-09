<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('admins can authenticate using the login screen', function () {
    $user = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated('admin');
    $response->assertRedirect(route('dashboard', absolute: false));
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $response->assertSessionHas('login.id', $user->id);
    $this->assertGuest('admin');
});

test('admins can not authenticate with invalid password', function () {
    $user = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);

    $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $this->assertGuest('admin');
});

test('admins can logout', function () {
    $user = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);

    $response = $this->actingAs($user, 'admin')->post(route('logout'));

    $response->assertRedirect(route('login'));

    $this->assertGuest('admin');
});

test('admins are rate limited', function () {
    $user = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
