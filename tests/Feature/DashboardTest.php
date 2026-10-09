<?php

use App\Models\Admin;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated admins can visit the dashboard', function () {
    $user = Admin::create(['name' => 'Admin', 'email' => 'admin@example.com', 'password' => 'password']);
    $this->actingAs($user, 'admin');

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
