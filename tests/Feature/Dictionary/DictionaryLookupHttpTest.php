<?php

use App\Actions\Admin\AssignAdminRole;
use App\Models\Admin;
use App\Models\User;
use Database\Seeders\AdminAuthorizationSeeder;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Spatie\Permission\Models\Role;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(fn () => config(['services.dictionary.lookup_mode' => 'provider', 'services.dictionary.provider' => 'free_dictionary']));

beforeEach(function () {
    $this->seed(AdminAuthorizationSeeder::class);
    $this->admin = Admin::create(['name' => 'Dictionary reader', 'email' => 'reader@example.test', 'password' => 'temporary-test-password']);
    $this->role = Role::create(['name' => 'dictionary-reader', 'guard_name' => 'admin']);
    app(AssignAdminRole::class)->handle($this->admin, $this->role);
});

afterEach(function () {
    $this->assertDatabaseCount('model_has_permissions', 0);
});

test('dictionary pages isolate guests web users and admins without the capability', function () {
    $this->get('/admin/dictionary/lookup')->assertRedirect('/admin/login');
    $this->actingAs(User::factory()->create(), 'web')->get('/admin/dictionary/lookup')->assertRedirect('/admin/login');
    $this->actingAs($this->admin, 'admin')->get('/admin/dictionary/lookup')->assertForbidden();
    $this->role->givePermissionTo('dictionary.view');
    $this->get('/admin/dictionary/lookup')->assertOk()->assertInertia(fn (Assert $page) => $page->component('admin/dictionary/lookup')->where('available', true)->where('entry', null)->where('query', '')->where('capabilities', fn ($capabilities) => $capabilities['dictionary.view'] === true));
});

test('an authorized admin can read exact entries with attribution and clear missing states', function () {
    Http::fake(['https://api.dictionaryapi.dev/*' => Http::response([
        ['word' => 'run', 'meanings' => [['partOfSpeech' => 'verb', 'definitions' => [['definition' => 'Move quickly on foot.']]]]],
    ])]);
    $this->role->givePermissionTo('dictionary.view');
    $this->actingAs($this->admin, 'admin')->get('/admin/dictionary/lookup?word=run&language=en')->assertOk()->assertInertia(fn (Assert $page) => $page
        ->component('admin/dictionary/lookup')->where('query', 'run')->where('available', true)
        ->where('entry.headword', 'run')->has('entry.lexemes', 1)->has('entry.lexemes.0.senses', 1)
        ->where('entry.attribution.text', 'Definitions and pronunciations provided by Free Dictionary API.'));
    Http::fake(['https://api.dictionaryapi.dev/*' => Http::response([], 404)]);
    $this->get('/admin/dictionary/lookup?word=missing-new')->assertInertia(fn (Assert $page) => $page->where('query', 'missing-new')->where('available', true));
    $this->role->revokePermissionTo('dictionary.view');
    $this->get('/admin/dictionary/lookup?word=run')->assertForbidden();
});

test('super admin dictionary access uses Gate without explicit permission assignment', function () {
    app(AssignAdminRole::class)->handle($this->admin, Role::findByName('super-admin', 'admin'));
    $this->actingAs($this->admin, 'admin')->get('/admin/dictionary/lookup')->assertOk();
    $this->assertDatabaseCount('role_has_permissions', 0);
});

test('lookup validates spelling language and unsupported fields', function (array $query, string $field) {
    $this->role->givePermissionTo('dictionary.view');
    $this->actingAs($this->admin, 'admin')->from('/admin/dictionary/lookup')->get('/admin/dictionary/lookup?'.http_build_query($query))->assertSessionHasErrors($field);
})->with([
    [['word' => '   '], 'word'],
    [['word' => str_repeat('a', 256)], 'word'],
    [['word' => ['run']], 'word'],
    [['word' => 'run', 'language' => 'fr'], 'language'],
    [['word' => 'run', 'release_id' => 2], 'release_id'],
]);

test('the dictionary exposes no HTTP write endpoint', function () {
    $this->role->givePermissionTo('dictionary.view');
    $this->actingAs($this->admin, 'admin')->post('/admin/dictionary/lookup', ['word' => 'run'])->assertStatus(405);
    $this->delete('/admin/dictionary/lookup')->assertStatus(405);
});
