<?php

namespace App\Console\Commands;

use App\Actions\Dictionary\ActivateDictionaryRelease;
use App\Actions\Dictionary\ImportDictionaryFixture as ImportFixture;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;

class ImportDictionaryFixture extends Command
{
    protected $signature = 'app:import-dictionary-fixture {artifact : Local JSONL path} {manifest : Local manifest path} {--activate : Make this ready fixture visible in local/testing}';

    protected $description = 'Import a tiny attributed English dictionary fixture (at most 1 MiB / 100 records)';

    public function handle(ImportFixture $import, ActivateDictionaryRelease $activate): int
    {
        if (! app()->environment(['local', 'testing'])) {
            $this->error('Fixture imports are available only in local/testing environments.');

            return self::FAILURE;
        }
        try {
            $run = $import->handle($this->argument('artifact'), $this->argument('manifest'));
            if ($run->status !== 'completed') {
                $this->error('Import run '.$run->id.' failed: '.$run->summary);

                return self::FAILURE;
            }
            if ($this->option('activate')) {
                $release = $run->release()->firstOrFail();
                $activate->handle($release->source()->firstOrFail(), $release);
            }
            $this->info("Run {$run->id}: {$run->records_inserted} inserted, {$run->records_unchanged} unchanged, {$run->records_skipped} skipped.");
            $this->info($this->option('activate') ? 'Fixture activated.' : 'Ready; not activated.');

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error('Invalid manifest fields: '.implode(', ', array_keys($exception->errors())));
        } catch (InvalidArgumentException|JsonException $exception) {
            $this->error($exception->getMessage());
        }

        return self::FAILURE;
    }
}
