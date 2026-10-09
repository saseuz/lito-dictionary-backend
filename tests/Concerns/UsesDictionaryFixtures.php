<?php

namespace Tests\Concerns;

use App\Actions\Dictionary\ActivateDictionaryRelease;
use App\Actions\Dictionary\ImportDictionaryFixture;
use App\Models\Dictionary\ImportRun;
use App\Support\Dictionary\DictionaryText;

trait UsesDictionaryFixtures
{
    private array $dictionaryTemporaryFiles = [];

    public function dictionaryFixture(bool $activate = true): ImportRun
    {
        $run = app(ImportDictionaryFixture::class)->handle(database_path('fixtures/dictionary/english-tiny.jsonl'), database_path('fixtures/dictionary/english-tiny.manifest.json'));
        expect($run->status)->toBe('completed', $run->summary ?? '');
        if ($activate) {
            app(ActivateDictionaryRelease::class)->handle($run->release->source, $run->release);
        }

        return $run;
    }

    public function dictionaryFiles(array|string $records, array $changes = []): array
    {
        $artifact = is_string($records) ? $records : implode("\n", array_map(DictionaryText::canonical(...), $records))."\n";
        $manifest = json_decode(file_get_contents(database_path('fixtures/dictionary/english-tiny.manifest.json')), true);
        $manifest['artifact_sha256'] = hash('sha256', $artifact);
        $manifest['pages'] = is_array($records) ? array_values(array_unique(array_map(fn ($record) => $record['original_title'] ?? $record['word'] ?? 'invalid', $records))) : ['run'];
        $manifest = array_replace($manifest, $changes);
        $paths = [tempnam(sys_get_temp_dir(), 'lito-fixture-'), tempnam(sys_get_temp_dir(), 'lito-manifest-')];
        file_put_contents($paths[0], $artifact);
        file_put_contents($paths[1], DictionaryText::canonical($manifest));
        array_push($this->dictionaryTemporaryFiles, ...$paths);

        return $paths;
    }

    public function tearDownUsesDictionaryFixtures(): void
    {
        foreach ($this->dictionaryTemporaryFiles as $path) {
            if (is_file($path)) {
                unlink($path);
            }
        }
    }
}
