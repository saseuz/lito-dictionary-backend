<?php

use App\Models\Admin;
use App\Models\User;
use Illuminate\Contracts\Auth\Guard;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    config(['session.driver' => 'database', 'session.connection' => 'sqlite']);

    $this->admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => 'test-password',
    ]);
});

// Carry only the browser cookie forward, not cached guards or session objects.
function continueAdminBrowserSession(TestResponse $response): string
{
    $sessionId = $response->getCookie(config('session.cookie'))->getValue();
    Auth::forgetGuards();
    app()->forgetInstance('auth.driver');
    app('session')->forgetDrivers();
    app()->forgetInstance('session.store');
    test()->withCookie(config('session.cookie'), $sessionId);

    return $sessionId;
}

test('admin database login regenerates the session and persists the UUID and identity', function () {
    // Even an earlier resolution must not leave the web guard cached for login.
    expect(app(Guard::class))->toBe(Auth::guard('web'));
    $guest = $this->get('/admin/login')->assertOk();
    $oldId = continueAdminBrowserSession($guest);

    $response = $this->post('/admin/login', [
        'email' => $this->admin->email,
        'password' => 'test-password',
        'remember' => true,
    ])->assertSessionHasNoErrors()->assertRedirect('/admin/dashboard');

    $response->assertCookie(Auth::guard('admin')->getRecallerName());
    expect($this->admin->fresh()->remember_token)->not->toBeNull();
    $newId = continueAdminBrowserSession($response);
    expect($newId)->not->toBe($oldId);
    $this->assertDatabaseMissing('sessions', ['id' => $oldId]);
    $this->assertDatabaseHas('sessions', ['id' => $newId, 'user_id' => $this->admin->id]);

    $this->get('/admin/dashboard')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('dashboard')
        ->where('auth.guard', 'admin')
        ->where('auth.user.id', $this->admin->id)
        ->missing('auth.user.password')
        ->missing('auth.user.remember_token'));
});

test('invalid admin credentials and user-only credentials fail', function (string $email, string $password) {
    User::factory()->create(['email' => 'user@example.com', 'password' => 'test-password']);

    $response = $this->from('/admin/login')->post('/admin/login', compact('email', 'password'));
    $response->assertRedirect('/admin/login')->assertSessionHasErrors('email');
    continueAdminBrowserSession($response);
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
})->with([
    ['admin@example.com', 'wrong'],
    ['user@example.com', 'test-password'],
]);

test('a web-authenticated user cannot enter the admin dashboard or the former dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user, 'web');

    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
    $this->get('/dashboard')->assertNotFound();
    expect(Auth::getDefaultDriver())->toBe('web');
});

test('real failed login attempts trigger Fortify throttling', function () {
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->post('/admin/login', [
            'email' => $this->admin->email,
            'password' => 'wrong',
        ])->assertSessionHasErrors('email');
    }

    $this->post('/admin/login', [
        'email' => $this->admin->email,
        'password' => 'test-password',
    ])->assertTooManyRequests();
});

test('admin logout destroys authentication and redirects to admin login', function () {
    $login = $this->post('/admin/login', [
        'email' => $this->admin->email,
        'password' => 'test-password',
    ])->assertRedirect('/admin/dashboard');
    $id = continueAdminBrowserSession($login);

    $logout = $this->post('/admin/logout')->assertRedirect('/admin/login');
    $this->assertDatabaseMissing('sessions', ['id' => $id]);
    continueAdminBrowserSession($logout);
    $this->get('/admin/dashboard')->assertRedirect('/admin/login');
});

test('admin guard selection is restored for unrelated web requests', function () {
    Route::middleware('web')->get('/test-web-guard', function () {
        return response()->json([
            'guard' => Auth::getDefaultDriver(),
            'id' => app(Guard::class)->id(),
        ]);
    });

    $login = $this->post('/admin/login', [
        'email' => $this->admin->email,
        'password' => 'test-password',
    ])->assertRedirect('/admin/dashboard');
    // Check in the same application instance, without clearing the Guard binding.
    expect(Auth::getDefaultDriver())->toBe('web');
    expect(app(Guard::class))->toBe(Auth::guard('web'));
    $id = continueAdminBrowserSession($login);

    $this->get('/test-web-guard')->assertExactJson(['guard' => 'web', 'id' => null]);
    expect(DB::table('sessions')->find($id)->user_id)->toBeNull();
    $this->get('/admin/dashboard')->assertOk();
});

test('registration and disabled Fortify endpoints are not registered', function (string $method, string $path) {
    $this->call($method, '/admin'.$path)->assertNotFound();
    $this->call($method, $path)->assertNotFound();
})->with([
    ['GET', '/register'], ['POST', '/register'],
    ['GET', '/forgot-password'], ['POST', '/forgot-password'],
    ['GET', '/reset-password/token'], ['POST', '/reset-password'],
    ['GET', '/email/verify'], ['GET', '/email/verify/id/hash'],
    ['POST', '/email/verification-notification'],
    ['GET', '/two-factor-challenge'], ['POST', '/two-factor-challenge'],
    ['POST', '/user/two-factor-authentication'], ['DELETE', '/user/two-factor-authentication'],
    ['POST', '/user/confirmed-two-factor-authentication'],
    ['GET', '/user/two-factor-qr-code'], ['GET', '/user/two-factor-secret-key'],
    ['GET', '/user/two-factor-recovery-codes'], ['POST', '/user/two-factor-recovery-codes'],
    ['GET', '/passkeys/login/options'], ['POST', '/passkeys/login'],
    ['GET', '/passkeys/confirm/options'], ['POST', '/passkeys/confirm'],
    ['GET', '/user/passkeys/options'], ['POST', '/user/passkeys'],
    ['DELETE', '/user/passkeys/1'],
    ['GET', '/.well-known/passkey-endpoints'],
]);
