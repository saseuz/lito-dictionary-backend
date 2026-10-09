<?php

namespace App\Services\Dictionary;

use App\Contracts\DictionaryProvider;
use Illuminate\Support\Facades\Http;

class MerriamWebsterProvider implements DictionaryProvider
{
    public function lookup(string $word, string $language): array
    {
        $product = (string) config('services.dictionary.merriam_webster.product', 'learners');
        $key = (string) config("services.dictionary.merriam_webster.keys.{$product}", '');
        $baseUrl = (string) config("services.dictionary.merriam_webster.base_urls.{$product}", '');
        if ($language !== 'en' || $key === '' || $baseUrl === '') {
            return ['status' => 'error', 'entry' => null, 'attribution' => $this->attribution()];
        }

        try {
            $response = Http::acceptJson()->timeout((int) config('services.dictionary.timeout', 5))
                ->get(rtrim($baseUrl, '/').'/'.rawurlencode($word), ['key' => $key]);
            if ($response->status() === 404) {
                return ['status' => 'miss', 'entry' => null, 'attribution' => $this->attribution()];
            }
            $response->throw();
            $payload = $response->json();
            if (! is_array($payload)) {
                return ['status' => 'error', 'entry' => null, 'attribution' => $this->attribution($product)];
            }
            if (! is_array($payload[0] ?? null)) {
                return ['status' => 'miss', 'entry' => null, 'attribution' => $this->attribution()];
            }

            return ['status' => 'hit', 'entry' => $this->normalize($payload, $product), 'attribution' => $this->attribution($product)];
        } catch (\Throwable) {
            return ['status' => 'error', 'entry' => null, 'attribution' => $this->attribution()];
        }
    }

    /** @param array<int, mixed> $payload @return array<string, mixed> */
    private function normalize(array $payload, string $product): array
    {
        $first = $payload[0];
        $lexemes = [];
        foreach ($payload as $index => $item) {
            $definitions = $this->definitions($item['def'] ?? []);
            if ($definitions === []) {
                $definitions = array_values(array_filter($item['shortdef'] ?? [], 'is_string'));
            }
            $lexemes[] = ['id' => $index, 'partOfSpeech' => (string) ($item['fl'] ?? 'unknown'), 'etymology' => null, 'etymologyKey' => null, 'sourceUrl' => $this->attribution($product)['url'], 'pronunciations' => [], 'forms' => [], 'relations' => [], 'senses' => array_map(fn (string $definition, int $senseId): array => ['id' => $senseId, 'definitions' => [$definition], 'tags' => [], 'examples' => [], 'relations' => []], $definitions, array_keys($definitions))];
        }

        return ['headword' => (string) ($first['meta']['id'] ?? ''), 'language' => 'English', 'lexemes' => $lexemes, 'attribution' => [
            'name' => $this->attribution($product)['source'],
            'text' => 'Definitions provided by '.$this->attribution($product)['source'].'.', 'license' => 'Merriam-Webster API terms', 'licenseUrl' => 'https://dictionaryapi.com/info/terms-of-service', 'sourceUrl' => $this->attribution($product)['url'], 'releaseKey' => "merriam-webster-{$product}", 'artifactKind' => 'provider', 'dumpDate' => null, 'extractorVersion' => 'provider', 'transformations' => 'Normalized by Lito Dictionary.',
        ]];
    }

    /** @param array<int, mixed> $definitions @return list<string> */
    private function definitions(array $definitions): array
    {
        $texts = [];
        foreach ($definitions as $definition) {
            foreach (($definition['sseq'] ?? []) as $sequence) {
                foreach ($sequence as $sense) {
                    if (($sense[0] ?? null) === 'sense') {
                        foreach (($sense[1]['dt'] ?? []) as $detail) {
                            if (($detail[0] ?? null) === 'text' && is_string($detail[1] ?? null)) {
                                $texts[] = trim(preg_replace('/\{[^}]+\}/', '', $detail[1]) ?? $detail[1]);
                            }
                        }
                    }
                }
            }
        }

        return array_values(array_filter($texts));
    }

    /** @return array{source: string, url: string, terms: string} */
    private function attribution(string $product = 'learners'): array
    {
        $title = $product === 'intermediate' ? 'Intermediate' : "Learner's";

        return ['source' => "Merriam-Webster's {$title} Dictionary", 'url' => "https://www.dictionaryapi.com/products/api-{$product}-dictionary", 'terms' => 'https://dictionaryapi.com/info/terms-of-service'];
    }
}
