<?php

namespace App\Support\Dictionary;

use Illuminate\Support\Facades\Validator;
use InvalidArgumentException;
use stdClass;

class FixtureInput
{
    public const ADAPTER_VERSION = 'en-kaikki-fixture-v1';

    public const SCOPE = 'en-v1';

    /**
     * @param  array<string, mixed>  $manifest
     * @param  list<string>  $lines
     */
    private function __construct(
        public readonly array $manifest,
        public readonly string $manifestJson,
        public readonly array $lines,
        public readonly string $artifactHash,
        public readonly string $manifestHash,
        public readonly string $releaseKey,
    ) {}

    public static function read(string $artifactPath, string $manifestPath): self
    {
        $artifact = self::localFile($artifactPath, 1048576);
        $manifestJson = self::localFile($manifestPath, 65536);
        $object = json_decode($manifestJson, false, 64, JSON_THROW_ON_ERROR);
        if (! $object instanceof stdClass) {
            throw new InvalidArgumentException('The fixture manifest must be a JSON object.');
        }
        $data = json_decode($manifestJson, true, 64, JSON_THROW_ON_ERROR);
        Validator::make($data, [
            'schema_version' => ['required', 'integer', 'in:1'],
            'artifact_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'adapter_version' => ['required', 'in:'.self::ADAPTER_VERSION],
            'scope_key' => ['required', 'in:'.self::SCOPE],
            'artifact_kind' => ['required', 'in:fixture'],
            'source_url' => ['required', 'in:https://kaikki.org/dictionary/rawdata.html'],
            'license_identifier' => ['required', 'in:CC-BY-SA-4.0'],
            'license_url' => ['required', 'in:https://creativecommons.org/licenses/by-sa/4.0/'],
            'attribution_text' => ['required', 'string', 'max:2000'],
            'transformations' => ['required', 'string', 'max:4000'],
            'extractor_version' => ['required', 'string', 'max:255'],
            'upstream_dump_date' => ['nullable', 'date_format:Y-m-d'],
            'extracted_at' => ['nullable', 'date_format:Y-m-d\TH:i:s\Z'],
            'pages' => ['required', 'array', 'list', 'max:100'],
            'pages.*' => ['required', 'string', 'max:255'],
            'reviewed_examples' => ['present', 'array', 'list', 'max:100'],
            'reviewed_examples.*.record_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reviewed_examples.*.path' => ['required', 'regex:/^senses\.[0-9]+\.examples\.[0-9]+$/'],
            'reviewed_examples.*.text_sha256' => ['required', 'regex:/^[a-f0-9]{64}$/'],
            'reviewed_examples.*.license_identifier' => ['required', 'in:CC-BY-SA-4.0'],
            'reviewed_examples.*.license_url' => ['required', 'in:https://creativecommons.org/licenses/by-sa/4.0/'],
            'reviewed_examples.*.attribution_text' => ['required', 'string', 'max:2000'],
            'reviewed_examples.*.rights_note' => ['required', 'string', 'max:2000'],
        ])->validate();
        $artifactHash = hash('sha256', $artifact);
        if (! hash_equals($data['artifact_sha256'], $artifactHash)) {
            throw new InvalidArgumentException('The artifact checksum does not match its manifest.');
        }
        $lines = explode("\n", $artifact);
        $count = count(array_filter($lines, fn (string $line): bool => trim($line) !== ''));
        if ($count < 1 || $count > 100) {
            throw new InvalidArgumentException('A fixture must contain 1–100 nonblank JSONL records.');
        }
        $canonical = DictionaryText::canonical($object);
        $manifestHash = hash('sha256', $canonical);
        $releaseKey = hash('sha256', DictionaryText::canonical([
            'artifact_sha256' => $artifactHash,
            'manifest_sha256' => $manifestHash,
            'adapter_version' => self::ADAPTER_VERSION,
            'scope_key' => self::SCOPE,
        ]));

        return new self($data, $canonical, $lines, $artifactHash, $manifestHash, $releaseKey);
    }

    /** @param positive-int $limit */
    private static function localFile(string $path, int $limit): string
    {
        $resolved = realpath($path);
        if ($resolved === false || ! is_file($resolved) || ! is_readable($resolved)) {
            throw new InvalidArgumentException('Provide a readable local fixture and manifest file.');
        }
        $bytes = file_get_contents($resolved, false, null, 0, $limit + 1);
        if ($bytes === false || strlen($bytes) > $limit) {
            throw new InvalidArgumentException('Fixture limit: 1 MiB artifact and 64 KiB manifest.');
        }

        return $bytes;
    }
}
