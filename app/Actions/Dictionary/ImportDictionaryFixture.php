<?php

namespace App\Actions\Dictionary;

use App\Models\Dictionary\ImportRun;
use App\Models\Dictionary\Release;
use App\Models\Dictionary\SemanticRelation;
use App\Models\Dictionary\Source;
use App\Models\Dictionary\Word;
use App\Support\Dictionary\DictionaryText;
use App\Support\Dictionary\FixtureInput;
use App\Support\Dictionary\ReleaseIntegrity;
use App\Support\Dictionary\SourceLock;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;
use JsonException;
use RuntimeException;
use stdClass;
use Throwable;

class ImportDictionaryFixture
{
    public function __construct(private WriteKaikkiEntry $writer, private ReleaseIntegrity $integrity) {}

    public function handle(string $artifactPath, string $manifestPath): ImportRun
    {
        // Preflight is read-only: a trustworthy identity is needed before recording a release.
        $input = FixtureInput::read($artifactPath, $manifestPath);
        $manifest = $input->manifest;
        $source = Source::firstOrCreate(['key' => 'wiktionary-en-kaikki'], [
            'name' => 'English Wiktionary via Kaikki', 'homepage_url' => 'https://kaikki.org/dictionary/rawdata.html',
            'default_license_identifier' => $manifest['license_identifier'], 'default_license_url' => $manifest['license_url'],
            'default_attribution_text' => $manifest['attribution_text'],
        ]);
        $release = Release::firstOrCreate(['source_id' => $source->id, 'release_key' => $input->releaseKey], [
            'artifact_sha256' => $input->artifactHash, 'manifest_sha256' => $input->manifestHash,
            'adapter_version' => FixtureInput::ADAPTER_VERSION, 'scope_key' => FixtureInput::SCOPE,
            'artifact_kind' => 'fixture', 'source_url' => $manifest['source_url'],
            'upstream_dump_date' => $manifest['upstream_dump_date'] ?? null, 'extracted_at' => $manifest['extracted_at'] ?? null,
            'extractor_version' => $manifest['extractor_version'], 'license_identifier' => $manifest['license_identifier'],
            'license_url' => $manifest['license_url'], 'attribution_text' => $manifest['attribution_text'], 'manifest' => $manifest, 'status' => 'staging',
        ]);
        $run = $release->runs()->create(['status' => 'running', 'started_at' => now()]);
        $counts = ['records_seen' => 0, 'records_inserted' => 0, 'records_unchanged' => 0, 'records_skipped' => 0, 'records_rejected' => 0];
        $lineNumber = null;
        $recordHash = null;
        try {
            DB::transaction(function () use ($input, $source, $release, $run, &$counts, &$lineNumber, &$recordHash): void {
                SourceLock::acquire($source->id);
                $release->refresh();
                $ready = $release->status === 'ready';
                if ($release->artifact_sha256 !== $input->artifactHash || $release->manifest_sha256 !== $input->manifestHash || $release->adapter_version !== FixtureInput::ADAPTER_VERSION || $release->scope_key !== FixtureInput::SCOPE) {
                    throw new RuntimeException('Stored release identity does not match the fixture.');
                }
                if ($ready && $release->content_sha256 !== $this->integrity->hash($release)) {
                    throw new RuntimeException('Ready release integrity check failed.');
                }
                $seenHashes = [];
                foreach ($input->lines as $index => $line) {
                    if (trim($line) === '') {
                        continue;
                    }
                    $lineNumber = $index + 1;
                    $recordHash = null;
                    $counts['records_seen']++;
                    $object = json_decode($line, false, 64, JSON_THROW_ON_ERROR);
                    if (! $object instanceof stdClass || ! isset($object->lang_code) || ! is_string($object->lang_code) || $object->lang_code === '') {
                        throw new InvalidArgumentException('Each record must be an object with a language code.');
                    }
                    if ($object->lang_code !== 'en') {
                        $counts['records_skipped']++;

                        continue;
                    }
                    $this->writer->validateStructure($object);
                    $canonical = DictionaryText::canonical($object);
                    $recordHash = hash('sha256', $canonical);
                    $data = json_decode($canonical, true, 64, JSON_THROW_ON_ERROR);
                    $this->writer->validate($data);
                    $existing = $release->lexemes()->where('source_record_hash', $recordHash)->first();
                    if ($existing) {
                        if (DictionaryText::canonical(json_decode($existing->getRawOriginal('raw_payload'), false, 64, JSON_THROW_ON_ERROR)) !== $canonical) {
                            throw new RuntimeException('Record hash collision.');
                        }
                        $counts['records_unchanged']++;
                    } else {
                        if ($ready) {
                            throw new RuntimeException('A ready release is missing an entry.');
                        }
                        $this->writer->handle($data, $canonical, $release, $input, $lineNumber);
                        $counts['records_inserted']++;
                    }
                    $seenHashes[$recordHash] = true;
                }
                if ($seenHashes === [] || count($seenHashes) !== $release->lexemes()->count()) {
                    throw new RuntimeException('The release must contain exactly the selected English records.');
                }
                if (! $ready) {
                    $this->resolveRelations($release);
                    $release->update(['status' => 'ready', 'ready_at' => now(), 'content_sha256' => $this->integrity->hash($release)]);
                }
                $run->update([...$counts, 'status' => 'completed', 'finished_at' => now(), 'summary' => 'Fixture verified and ready. Activation is separate.']);
            });
        } catch (Throwable $exception) {
            $counts['records_inserted'] = 0;
            $counts['records_unchanged'] = 0;
            $counts['records_rejected'] = $lineNumber === null ? 0 : 1;
            $message = match (true) {
                $exception instanceof ValidationException => 'Invalid entry fields: '.implode(', ', array_keys($exception->errors())),
                $exception instanceof JsonException => 'Malformed JSON or invalid UTF-8 in a record.',
                $exception instanceof InvalidArgumentException => $exception->getMessage(),
                default => 'Import failed validation, integrity, or storage checks. No content was committed.',
            };
            // Do not log exception SQL/payloads; preserve a bounded diagnostic outside the failed transaction.
            DB::transaction(function () use ($source, $release, $run, $counts, $message, $exception, $lineNumber, $recordHash): void {
                SourceLock::acquire($source->id);
                $release->refresh();
                if ($release->status !== 'ready') {
                    $release->update(['status' => 'failed']);
                }
                $run->update([...$counts, 'status' => 'failed', 'finished_at' => now(), 'summary' => mb_substr($message, 0, 1000)]);
                $run->errors()->create([
                    'line_number' => $lineNumber, 'source_record_hash' => $recordHash, 'code' => 'fixture_import_failed',
                    'message' => mb_substr($message, 0, 1000), 'context' => ['exception_type' => $exception::class], 'created_at' => now(),
                ]);
            });
        }

        return $run->refresh();
    }

    private function resolveRelations(Release $release): void
    {
        $ids = $release->lexemes()->pluck('id');
        foreach (SemanticRelation::whereIn('lexeme_id', $ids)->get() as $relation) {
            $target = Word::where('language_id', $relation->target_language_id)->where('headword_hash', $relation->target_hash)
                ->whereHas('lexemes', fn ($query) => $query->where('release_id', $release->id))->first();
            if ($target && $target->headword !== $relation->target_text) {
                throw new RuntimeException('Relation target hash collision.');
            }
            $relation->update(['target_word_id' => $target?->id]);
        }
    }
}
