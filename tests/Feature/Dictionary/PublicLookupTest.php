<?php

use App\Actions\Dictionary\LookupWord;
use App\Actions\Dictionary\ProviderLookup;
use App\Http\Controllers\Api\DictionaryController;
use App\Models\DictionaryResult;
use App\Services\Dictionary\PublicFixtureProvider;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

// Exercise the shared database-first path with our own licensed content.
beforeEach(function () {
    $this->app->when(DictionaryController::class)
        ->needs(LookupWord::class)
        ->give(fn () => new LookupWord(new ProviderLookup(app(PublicFixtureProvider::class), 'lito-demo-v1')));
});

test('public lookup persists fixture results and serves the same attributed data without another provider call', function () {
    Http::preventStrayRequests();
    $first = $this->getJson('/api/v1/dictionary/lookup?word=%20hello%20&language=EN')
        ->assertOk()->assertJsonPath('data.origin', 'provider')
        ->assertJsonPath('data.source.license', 'CC0-1.0')
        ->assertJsonPath('data.meanings.0.pronunciations.0.audio_url', null)
        ->assertCookieMissing(config('session.cookie'))->json('data');
    $this->mock(PublicFixtureProvider::class)->shouldNotReceive('lookup');
    $second = $this->getJson('/api/v1/dictionary/lookup?word=hello')
        ->assertOk()->assertJsonPath('data.origin', 'local')->json('data');
    unset($first['origin'], $second['origin']);
    expect($second)->toBe($first);
    expect(DictionaryResult::count())->toBe(1);
    Http::assertNothingSent();
});

test('public lookup never serves unapproved stored provider content', function () {
    $provider = new PublicFixtureProvider;
    (new ProviderLookup($provider, 'merriam_webster'))->handle('hello', 'en');
    $this->mock(PublicFixtureProvider::class)->shouldReceive('lookup')->once()->with('hello', 'en')
        ->andReturn(['status' => 'miss', 'entry' => null, 'attribution' => ['terms' => 'https://example.test']]);
    $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertNotFound()->assertJsonPath('error.code', 'not_found');
});

test('public provider failures return safe errors and are not stored', function () {
    $this->mock(PublicFixtureProvider::class)->shouldReceive('lookup')->once()
        ->andReturn(['status' => 'error', 'entry' => null, 'attribution' => []]);
    $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertStatus(503)
        ->assertExactJson(['error' => ['code' => 'provider_unavailable', 'message' => 'Dictionary provider unavailable. Please try again later.']]);
    expect(DictionaryResult::count())->toBe(0);
});

test('public lookup validates input and handles missing words', function () {
    foreach (['', '?word[]=hello', '?word=%20', '?word=hello&language=fr'] as $query) {
        $this->getJson('/api/v1/dictionary/lookup'.$query)->assertUnprocessable();
    }
    $this->getJson('/api/v1/dictionary/lookup?word=unknown')->assertNotFound();
    $this->postJson('/api/v1/dictionary/lookup', ['word' => 'hello'])->assertStatus(405);
});

test('public lookup limits requests per IP', function () {
    for ($i = 0; $i < 30; $i++) {
        $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertOk();
    }
    $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
});
