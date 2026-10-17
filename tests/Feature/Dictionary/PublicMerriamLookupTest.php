<?php

use App\Actions\Dictionary\ProviderLookup;
use App\Models\DictionaryResult;
use App\Services\Dictionary\PublicFixtureProvider;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(function () {
    config(['services.dictionary.merriam_webster.product' => 'learners', 'services.dictionary.merriam_webster.keys.learners' => 'test-secret']);
    Http::preventStrayRequests();
});

test('public Merriam requests use live results without reading or writing permanent records', function () {
    (new ProviderLookup(new PublicFixtureProvider, 'merriam_webster'))->handle('hello', 'en');
    $before = DictionaryResult::first()->getAttributes();
    Http::fake(['www.dictionaryapi.com/*' => Http::response([[
        'meta' => ['id' => 'hello'], 'fl' => 'interjection', 'shortdef' => ['Original synthetic test definition.'],
    ]])]);
    for ($i = 0; $i < 2; $i++) {
        $response = $this->getJson('/api/v1/dictionary/lookup?word=%20hello%20&language=EN')
            ->assertOk()->assertJsonPath('data.origin', 'provider')
            ->assertJsonPath('data.meanings.0.senses.0.definitions.0', 'Original synthetic test definition.')
            ->assertJsonPath('data.source.name', "Merriam-Webster's Learner's Dictionary")
            ->assertJsonPath('data.source.license_url', 'https://dictionaryapi.com/info/terms-of-service')
            ->assertCookieMissing(config('session.cookie'));
        expect($response->headers->get('Cache-Control'))->toContain('no-store');
        expect($response->getContent())->not->toContain('test-secret');
    }
    Http::assertSentCount(2);
    expect(DictionaryResult::count())->toBe(1);
    expect(DictionaryResult::first()->getAttributes())->toBe($before);
});

test('public Merriam failures and suggestions are safe and never stored', function () {
    Http::fakeSequence()->push(['suggestion'])->push('private provider failure', 500)->push('malformed', 200);
    $this->getJson('/api/v1/dictionary/lookup?word=unknown')->assertNotFound();
    foreach (['cat', 'hello'] as $word) {
        $this->getJson('/api/v1/dictionary/lookup?word='.$word)->assertStatus(503)
            ->assertJsonPath('error.code', 'provider_unavailable');
    }
    expect(DictionaryResult::count())->toBe(0);
});

test('missing public provider credentials fail without an external request', function () {
    config(['services.dictionary.merriam_webster.keys.learners' => '']);
    $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertStatus(503);
    Http::assertNothingSent();
});

test('public lookup preserves sense examples and text pronunciations without audio or external quotations', function () {
    Http::fake(['www.dictionaryapi.com/*' => Http::response([[
        'meta' => ['id' => 'hello:1'],
        'hwi' => ['hw' => 'hel*lo', 'prs' => [['ipa' => 'həˈloʊ', 'sound' => ['audio' => 'private-audio']], ['mw' => 'he-LOH'], ['sound' => ['audio' => 'audio-only']]]],
        'fl' => 'interjection',
        'def' => [['sseq' => [
            [['sense', ['dt' => [
                ['text', '{bc}An original {it}greeting{/it} for a {a_link|friend}.'],
                ['vis', [['t' => '{it}Hello{/it}, Mira!'], ['t' => 'Excluded quotation.', 'aq' => ['auth' => 'External author']]]],
            ]]]],
            [['pseq', [[['sense', ['dt' => [['text', '{bc}A second synthetic sense.'], ['vis', [['t' => 'A second example.']]]]]]]]]],
        ]]],
    ]])]);

    $response = $this->getJson('/api/v1/dictionary/lookup?word=hello')->assertOk()
        ->assertJsonPath('data.word', 'hello')
        ->assertJsonPath('data.meanings.0.pronunciations.0.text', 'həˈloʊ')
        ->assertJsonPath('data.meanings.0.pronunciations.0.notation', 'ipa')
        ->assertJsonPath('data.meanings.0.pronunciations.0.audio_url', null)
        ->assertJsonPath('data.meanings.0.pronunciations.1.notation', 'mw')
        ->assertJsonCount(2, 'data.meanings.0.pronunciations')
        ->assertJsonPath('data.meanings.0.senses.0.definitions.0', 'An original greeting for a friend.')
        ->assertJsonCount(1, 'data.meanings.0.senses.0.examples')
        ->assertJsonPath('data.meanings.0.senses.0.examples.0.text', 'Hello, Mira!')
        ->assertJsonPath('data.meanings.0.senses.0.examples.0.attribution', "Merriam-Webster's Learner's Dictionary")
        ->assertJsonPath('data.meanings.0.senses.1.examples.0.text', 'A second example.');

    expect($response->getContent())->not->toContain('private-audio', 'Excluded quotation', '{it}');
    expect(DictionaryResult::count())->toBe(0);
});

test('public lookup supports anonymous browser CORS without credentialed sessions', function () {
    Http::fake(['www.dictionaryapi.com/*' => Http::response([[
        'meta' => ['id' => 'hello'], 'shortdef' => ['A synthetic greeting.'],
    ]])]);
    $response = $this->withHeader('Origin', 'http://localhost:3000')->getJson('/api/v1/dictionary/lookup?word=hello')
        ->assertOk()->assertHeader('Access-Control-Allow-Origin', '*')
        ->assertHeaderMissing('Access-Control-Allow-Credentials')
        ->assertJsonPath('data.meanings.0.pronunciations', [])
        ->assertJsonPath('data.meanings.0.senses.0.examples', []);
    expect($response->headers->get('Cache-Control'))->toContain('no-store');
});
