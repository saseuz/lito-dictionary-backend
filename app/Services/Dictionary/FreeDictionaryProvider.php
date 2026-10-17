<?php

namespace App\Services\Dictionary;

use App\Contracts\DictionaryProvider;
use Illuminate\Support\Facades\Http;

class FreeDictionaryProvider implements DictionaryProvider
{
    public function lookup(string $word, string $language): array
    {
        if ($language !== 'en') {
            return ['status' => 'miss', 'entry' => null, 'attribution' => $this->attribution()];
        }
        try {
            $response = Http::acceptJson()->timeout((int) config('services.dictionary.timeout', 5))
                ->get('https://api.dictionaryapi.dev/api/v2/entries/en/'.rawurlencode($word));
            if ($response->status() === 404) {
                return ['status' => 'miss', 'entry' => null, 'attribution' => $this->attribution()];
            }
            $response->throw();
            $payload = $response->json();
            if (! is_array($payload)) {
                return ['status' => 'error', 'entry' => null, 'attribution' => $this->attribution()];
            }

            return ['status' => 'hit', 'entry' => $this->normalize($payload), 'attribution' => $this->attribution()];
        } catch (\Throwable) {
            return ['status' => 'error', 'entry' => null, 'attribution' => $this->attribution()];
        }
    }

    /**
     * @param  array<int, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload): array
    {
        $first = is_array($payload[0] ?? null) ? $payload[0] : [];
        $lexemes = [];
        foreach ($payload as $entry) {
            foreach (($entry['meanings'] ?? []) as $meaning) {
                $senses = [];
                foreach (($meaning['definitions'] ?? []) as $definition) {
                    if (is_array($definition) && isset($definition['definition'])) {
                        $senses[] = [
                            'id' => count($senses),
                            'definitions' => [$definition['definition']],
                            'tags' => [],
                            'examples' => ($definition['example'] ?? null) ? [[
                                'text' => $definition['example'], 'reference' => null,
                                'attribution' => 'Free Dictionary API', 'license' => 'Provider content', 'licenseUrl' => 'https://dictionaryapi.dev/',
                            ]] : [],
                            'relations' => [],
                        ];
                    }
                }
                $lexemes[] = [
                    'id' => count($lexemes), 'partOfSpeech' => (string) ($meaning['partOfSpeech'] ?? 'unknown'),
                    'etymology' => null, 'etymologyKey' => null, 'sourceUrl' => 'https://dictionaryapi.dev/',
                    'senses' => $senses, 'pronunciations' => [], 'forms' => [], 'relations' => [],
                ];
            }
        }
        foreach (($first['phonetics'] ?? []) as $sound) {
            if (! empty($sound['text'])) {
                $lexemes[0]['pronunciations'][] = ['notation' => 'ipa', 'text' => $sound['text'], 'form' => null, 'tags' => []];
            }
        }

        return ['headword' => (string) ($first['word'] ?? ''), 'language' => 'English', 'lexemes' => $lexemes, 'attribution' => [
            'text' => 'Definitions and pronunciations provided by Free Dictionary API.', 'license' => 'Provider content', 'licenseUrl' => 'https://dictionaryapi.dev/',
            'sourceUrl' => 'https://dictionaryapi.dev/', 'releaseKey' => 'free-dictionary-api', 'artifactKind' => 'provider',
            'dumpDate' => null, 'extractorVersion' => 'provider', 'transformations' => 'Normalized by Lito Dictionary.',
        ]];
    }

    /** @return array{source: string, url: string, terms: string} */
    private function attribution(): array
    {
        return ['source' => 'Free Dictionary API', 'url' => 'https://dictionaryapi.dev/', 'terms' => 'https://dictionaryapi.dev/'];
    }
}
