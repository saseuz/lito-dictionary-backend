<?php

use App\Actions\Dictionary\LookupWord;
use App\Models\DictionaryResult;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class);

beforeEach(fn () => config(['services.dictionary.lookup_mode' => 'provider', 'services.dictionary.provider' => 'free_dictionary']));

test('free dictionary provider fetches, normalizes, and caches a word', function () {
    Http::fake(['https://api.dictionaryapi.dev/*' => Http::response([
        ['word' => 'hello', 'phonetics' => [['text' => 'həˈləʊ']], 'meanings' => [[
            'partOfSpeech' => 'noun', 'definitions' => [['definition' => 'A greeting.', 'example' => 'Hello there.']],
        ]]],
    ])]);

    $result = app(LookupWord::class)->handle('hello');
    expect($result['available'])->toBeTrue()->and($result['entry']['headword'])->toBe('hello');
    expect($result['entry']['lexemes'][0]['senses'][0]['definitions'])->toBe(['A greeting.']);
    expect(DictionaryResult::first()->provider)->toBe('free_dictionary');

    Http::preventStrayRequests();
    $cached = app(LookupWord::class)->handle('hello');
    expect($cached['entry']['headword'])->toBe('hello');
});

test('provider misses are cached but failures are not persisted', function () {
    Http::fake([
        'https://api.dictionaryapi.dev/*/missing' => Http::response([], 404),
        'https://api.dictionaryapi.dev/*/offline' => Http::failedConnection(),
    ]);

    expect(app(LookupWord::class)->handle('missing')['entry'])->toBeNull();
    expect(DictionaryResult::where('status', 'miss')->exists())->toBeTrue();
    expect(app(LookupWord::class)->handle('offline')['available'])->toBeFalse();
    expect(DictionaryResult::where('lookup', 'offline')->exists())->toBeFalse();
});

test('provider errors are retried instead of hiding a later successful result', function () {
    Http::fakeSequence('https://api.dictionaryapi.dev/*')
        ->push([], 500)
        ->push([['word' => 'cat', 'meanings' => [['partOfSpeech' => 'noun', 'definitions' => [['definition' => 'A small domesticated feline.']]]]]]);

    expect(app(LookupWord::class)->handle('cat')['entry'])->toBeNull();
    $result = app(LookupWord::class)->handle('cat');

    expect($result['available'])->toBeTrue()
        ->and($result['entry']['headword'])->toBe('cat');
});

test('provider cache keys keep exact case and language separate', function () {
    Http::fake(['*' => Http::response([['word' => 'Run', 'meanings' => []]])]);
    app(LookupWord::class)->handle('Run');
    app(LookupWord::class)->handle('run');
    expect(DictionaryResult::count())->toBe(2);
});

test('provider mode takes precedence over an active durable release', function () {
    Http::fake(['https://api.dictionaryapi.dev/*' => Http::response([
        ['word' => 'run', 'meanings' => [['partOfSpeech' => 'verb', 'definitions' => [['definition' => 'Live provider result.']]]]],
    ])]);

    $result = app(LookupWord::class)->handle('run');
    expect($result['entry']['lexemes'][0]['senses'][0]['definitions'])->toBe(['Live provider result.']);
});

test('merriam webster provider can be selected and normalized', function () {
    config(['services.dictionary.provider' => 'merriam_webster', 'services.dictionary.merriam_webster' => ['product' => 'learners', 'keys' => ['learners' => 'test-key'], 'base_urls' => ['learners' => 'https://www.dictionaryapi.com/api/v3/references/learners/json']]]);
    Http::fake(['*' => Http::response([[
        'meta' => ['id' => 'hello'], 'fl' => 'noun', 'def' => [],
    ]])]);

    $result = app(LookupWord::class)->handle('hello');

    expect($result['entry']['headword'])->toBe('hello');
});
