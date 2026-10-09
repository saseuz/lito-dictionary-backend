<?php

use App\Actions\Dictionary\ActivateDictionaryRelease;
use App\Actions\Dictionary\ImportDictionaryFixture;
use App\Actions\Dictionary\LookupWord;
use App\Models\Dictionary\Definition;
use App\Models\Dictionary\Example;
use App\Models\Dictionary\ImportError;
use App\Models\Dictionary\Lexeme;
use App\Models\Dictionary\SemanticRelation;
use App\Models\Dictionary\Sense;
use App\Models\Dictionary\Source;
use App\Models\Dictionary\Word;
use App\Support\Dictionary\DictionaryText;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\UsesDictionaryFixtures;
use Tests\Concerns\UsesIsolatedDatabase;

uses(UsesIsolatedDatabase::class, UsesDictionaryFixtures::class);

test('the attributed fixture imports twice without changing content identities', function () {
    $first = $this->dictionaryFixture();
    expect($first->records_seen)->toBe(6)->and($first->records_inserted)->toBe(4)->and($first->records_unchanged)->toBe(1)->and($first->records_skipped)->toBe(1)->and($first->records_rejected)->toBe(0);
    $ids = Lexeme::pluck('id')->all();
    $definitions = Definition::pluck('id')->all();
    $checksum = $first->release->content_sha256;
    $second = $this->dictionaryFixture();
    expect($second->id)->not->toBe($first->id);
    expect($second->records_inserted)->toBe(0)->and($second->records_unchanged)->toBe(5);
    expect(Lexeme::pluck('id')->all())->toBe($ids)->and(Definition::pluck('id')->all())->toBe($definitions);
    expect($second->release->content_sha256)->toBe($checksum);
    $this->assertDatabaseCount('dictionary_words', 3);
    $this->assertDatabaseCount('dictionary_lexemes', 4);
    $this->assertDatabaseCount('dictionary_senses', 5);
    $this->assertDatabaseCount('dictionary_definitions', 5);
    $this->assertDatabaseCount('dictionary_relations', 4);
    $this->assertDatabaseCount('dictionary_import_runs', 2);
    $this->assertDatabaseCount('dictionary_releases', 1);
    expect(SemanticRelation::where('target_text', 'runner')->first()->target_word_id)->toBeNull();
    expect(SemanticRelation::where('target_text', 'sprint')->first()->target_word_id)->toBe(Word::where('headword', 'sprint')->first()->id);
    expect(SemanticRelation::where('kind', 'form_of')->first()->sense_id)->not->toBeNull();
});

test('lookup returns normalized content and attribution only from the active release', function () {
    $run = $this->dictionaryFixture(false);
    expect(app(LookupWord::class)->handle('run'))->toBe(['available' => false, 'entry' => null]);
    app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release);
    $result = app(LookupWord::class)->handle(' run ');
    expect($result['entry']['headword'])->toBe('run');
    expect(array_column($result['entry']['lexemes'], 'partOfSpeech'))->toBe(['Verb', 'Noun']);
    expect($result['entry']['lexemes'][0]['senses'][0]['examples'])->toHaveCount(1);
    expect($result['entry']['lexemes'][0]['pronunciations'][1]['form'])->toBe('ran');
    expect($result['entry']['attribution']['license'])->toBe('CC-BY-SA-4.0');
    foreach (['Run', 'runs', 'absent'] as $word) {
        expect(app(LookupWord::class)->handle($word)['entry'])->toBeNull();
    }
    expect(app(LookupWord::class)->handle('ran')['entry']['headword'])->toBe('ran');
    expect(app(LookupWord::class)->handle('run', 'fr')['entry'])->toBeNull();
});

test('a later bad record rolls back content but retains a bounded error and the old active release', function () {
    $old = $this->dictionaryFixture();
    $files = $this->dictionaryFiles([
        ['word' => 'new-word', 'lang_code' => 'en', 'pos' => 'noun', 'senses' => [['glosses' => ['New.']]]],
        ['word' => 'bad', 'lang_code' => 'en', 'pos' => 'noun', 'senses' => [['glosses' => [42]]]],
    ]);
    $run = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($run->status)->toBe('failed')->and($run->records_inserted)->toBe(0)->and($run->records_unchanged)->toBe(0)->and($run->records_seen)->toBe(2)->and($run->records_rejected)->toBe(1);
    expect(ImportError::first()->line_number)->toBe(2);
    $this->assertDatabaseMissing('dictionary_words', ['headword' => 'new-word']);
    expect(Source::first()->active_release_id)->toBe($old->release_id);
    expect(fn () => app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release))->toThrow(InvalidArgumentException::class);
    $retry = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($retry->release_id)->toBe($run->release_id)->and($retry->id)->not->toBe($run->id);
});

test('changed content gets a new release and is invisible until explicit activation', function () {
    $old = $this->dictionaryFixture();
    $files = $this->dictionaryFiles([['word' => 'run', 'lang_code' => 'en', 'pos' => 'verb', 'senses' => [['glosses' => ['A revised test meaning.']]]]]);
    $next = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($next->status)->toBe('completed')->and($next->release_id)->not->toBe($old->release_id);
    expect(app(LookupWord::class)->handle('run')['entry']['lexemes'])->toHaveCount(2);
    app(ActivateDictionaryRelease::class)->handle($next->release->source, $next->release);
    expect(app(LookupWord::class)->handle('run')['entry']['lexemes'][0]['senses'][0]['definitions'])->toBe(['A revised test meaning.']);
    expect(app(LookupWord::class)->handle('ran')['entry'])->toBeNull();
    $this->assertDatabaseCount('dictionary_words', 3);
});

test('a changed manifest creates a release while unsupported adapter versions are rejected', function () {
    $old = $this->dictionaryFixture();
    $artifact = file_get_contents(database_path('fixtures/dictionary/english-tiny.jsonl'));
    $files = $this->dictionaryFiles($artifact, ['pages' => ['run', 'ran', 'sprint'], 'transformations' => 'New manifest notice.']);
    $next = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($next->status)->toBe('completed')->and($next->release_id)->not->toBe($old->release_id);
    $files = $this->dictionaryFiles($artifact, ['adapter_version' => 'unimplemented-v2']);
    expect(fn () => app(ImportDictionaryFixture::class)->handle(...$files))->toThrow(ValidationException::class);
});

test('Unicode identity preserves case and optional entry structure while retaining homonyms', function () {
    $base = ['word' => "cafe\u{0301}", 'lang_code' => 'en', 'pos' => 'noun'];
    $files = $this->dictionaryFiles([$base, [...$base, 'etymology_number' => '2', 'senses' => [['glosses' => ['Context.', 'Specific sense.']]]], ['word' => 'Run', 'lang_code' => 'en', 'pos' => 'unmapped']]);
    $run = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($run->status)->toBe('completed');
    app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release);
    $entry = app(LookupWord::class)->handle("\u{00A0}café\u{00A0}")['entry'];
    expect($entry['lexemes'])->toHaveCount(2)->and($entry['lexemes'][0]['senses'])->toBe([]);
    expect($entry['lexemes'][1]['senses'][0]['definitions'])->toBe(['Context.', 'Specific sense.']);
    expect(app(LookupWord::class)->handle('Run')['entry']['lexemes'][0]['partOfSpeech'])->toBe('Unknown');
    expect(app(LookupWord::class)->handle('run')['entry'])->toBeNull();
});

test('unreviewed examples and audio never reach the lookup payload', function () {
    $files = $this->dictionaryFiles([['word' => 'run', 'lang_code' => 'en', 'pos' => 'verb', 'sounds' => [['ipa' => '/r/', 'audio' => 'private-audio.ogg', 'ogg_url' => 'https://example.com/private-audio.ogg']], 'senses' => [['glosses' => ['Move.'], 'examples' => [['text' => 'UNREVIEWED QUOTATION', 'type' => 'quote', 'ref' => 'Unknown book'], ['text' => 'UNREVIEWED USAGE', 'type' => 'example']]]]]]);
    $run = app(ImportDictionaryFixture::class)->handle(...$files);
    app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release);
    expect(Example::where('publication_status', 'withheld')->count())->toBe(2);
    $json = json_encode(app(LookupWord::class)->handle('run'));
    expect($json)->not->toContain('UNREVIEWED')->not->toContain('private-audio')->not->toContain('raw_payload');
});

test('ready replay detects damaged children without rewriting or marking the release failed', function () {
    $run = $this->dictionaryFixture();
    Definition::first()->update(['text' => 'Unexpected modification.']);
    $replay = app(ImportDictionaryFixture::class)->handle(database_path('fixtures/dictionary/english-tiny.jsonl'), database_path('fixtures/dictionary/english-tiny.manifest.json'));
    expect($replay->status)->toBe('failed')->and($run->release->fresh()->status)->toBe('ready');
    expect(Definition::first()->text)->toBe('Unexpected modification.');
    expect(fn () => app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release))->toThrow(InvalidArgumentException::class);
});

test('activation rejects another source and fixture replacement of a snapshot or production data', function () {
    $run = $this->dictionaryFixture();
    $source = Source::first()->replicate();
    $source->key = 'different';
    $source->save();
    expect(fn () => app(ActivateDictionaryRelease::class)->handle($source, $run->release))->toThrow(InvalidArgumentException::class);
    $snapshot = $run->release->replicate();
    $snapshot->release_key = str_repeat('a', 64);
    $snapshot->artifact_kind = 'snapshot';
    $snapshot->save();
    $run->release->source->update(['active_release_id' => $snapshot->id]);
    expect(fn () => app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release))->toThrow(InvalidArgumentException::class);
    expect($run->release->source->fresh()->active_release_id)->toBe($snapshot->id);
    $this->app->instance('env', 'production');
    expect(fn () => app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release))->toThrow(InvalidArgumentException::class);
});

test('database constraints reject duplicate identities and relations attached to another lexeme sense', function () {
    $this->dictionaryFixture();
    $word = Word::first();
    expect(fn () => $word->replicate()->save())->toThrow(QueryException::class);
    $relation = SemanticRelation::whereNotNull('sense_id')->first();
    $otherSense = Sense::where('lexeme_id', '!=', $relation->lexeme_id)->first();
    expect(fn () => $relation->update(['sense_id' => $otherSense->id]))->toThrow(QueryException::class);
    expect(fn () => Word::whereKey($word->id)->delete())->toThrow(QueryException::class);
});

test('preflight rejects checksum mismatches file limits and nonlocal paths without database writes', function () {
    $files = $this->dictionaryFiles([['word' => 'run', 'lang_code' => 'en', 'pos' => 'verb']], ['artifact_sha256' => str_repeat('0', 64)]);
    expect(fn () => app(ImportDictionaryFixture::class)->handle(...$files))->toThrow(InvalidArgumentException::class);
    $files = $this->dictionaryFiles(str_repeat('x', 1048577));
    expect(fn () => app(ImportDictionaryFixture::class)->handle(...$files))->toThrow(InvalidArgumentException::class);
    $files = $this->dictionaryFiles(array_fill(0, 101, ['word' => 'run', 'lang_code' => 'en', 'pos' => 'verb']));
    expect(fn () => app(ImportDictionaryFixture::class)->handle(...$files))->toThrow(InvalidArgumentException::class);
    expect(fn () => app(ImportDictionaryFixture::class)->handle('https://example.com/file', $files[1]))->toThrow(InvalidArgumentException::class);
    $this->assertDatabaseCount('dictionary_import_runs', 0);
    $this->assertDatabaseCount('dictionary_releases', 0);
});

test('malformed JSON and selected field shapes produce durable failure diagnostics', function (string $artifact) {
    $files = $this->dictionaryFiles($artifact);
    $run = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($run->status)->toBe('failed');
    $this->assertDatabaseCount('dictionary_words', 0);
    $this->assertDatabaseCount('dictionary_import_errors', 1);
})->with(['broken JSON' => '{bad', 'object masquerading as list' => '{"word":"run","lang_code":"en","pos":"verb","senses":{}}', 'missing language' => '{}', 'bad senses' => '{"word":"run","lang_code":"en","pos":"verb","senses":"bad"}', 'no selected entries' => '{"lang_code":"fr","word":"run"}']);

test('canonical identity sorts objects but preserves array order and scalar types', function () {
    expect(DictionaryText::canonical(json_decode('{"b":1.0,"a":{}}')))->toBe('{"a":{},"b":1.0}');
    expect(DictionaryText::canonical([2, 1]))->not->toBe(DictionaryText::canonical([1, 2]));
    $record = json_decode('{"word":"run","lang_code":"en","pos":"verb","extra":{"b":1.0,"a":{}}}', true);
    $files = $this->dictionaryFiles([$record]);
    $first = app(ImportDictionaryFixture::class)->handle(...$files);
    $second = app(ImportDictionaryFixture::class)->handle(...$files);
    expect($first->status)->toBe('completed')->and($second->status)->toBe('completed')->and($second->records_unchanged)->toBe(1);
});

test('the fixture command imports and activates only when explicitly requested', function () {
    $arguments = ['artifact' => database_path('fixtures/dictionary/english-tiny.jsonl'), 'manifest' => database_path('fixtures/dictionary/english-tiny.manifest.json')];
    $this->artisan('app:import-dictionary-fixture', $arguments)->expectsOutput('Ready; not activated.')->assertSuccessful();
    expect(Source::first()->active_release_id)->toBeNull();
    $this->artisan('app:import-dictionary-fixture', [...$arguments, '--activate' => true])->expectsOutput('Fixture activated.')->assertSuccessful();
    expect(Source::first()->active_release_id)->not->toBeNull();
    $this->app->instance('env', 'production');
    $this->artisan('app:import-dictionary-fixture', $arguments)->assertFailed();
    $this->assertDatabaseCount('dictionary_import_runs', 2);
});
