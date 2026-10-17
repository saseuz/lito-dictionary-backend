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

    /**
     * @param  array<int, mixed>  $payload
     * @return array<string, mixed>
     */
    private function normalize(array $payload, string $product): array
    {
        $first = $payload[0];
        $lexemes = [];
        foreach ($payload as $index => $item) {
            $senses = $this->senses($item['def'] ?? [], $product);
            if ($senses === []) {
                $senses = array_map(fn (string $text): array => ['definitions' => [$this->plainText($text)], 'examples' => []], array_values(array_filter($item['shortdef'] ?? [], 'is_string')));
            }
            $lexemes[] = ['id' => $index, 'partOfSpeech' => (string) ($item['fl'] ?? 'unknown'), 'etymology' => null, 'etymologyKey' => null, 'sourceUrl' => $this->attribution($product)['url'], 'pronunciations' => $this->pronunciations($item['hwi']['prs'] ?? []), 'forms' => [], 'relations' => [], 'senses' => array_map(fn (array $sense, int $senseId): array => ['id' => $senseId, 'tags' => [], 'relations' => [], ...$sense], $senses, array_keys($senses))];
        }

        return ['headword' => str_replace('*', '', (string) ($first['hwi']['hw'] ?? preg_replace('/:\d+$/', '', (string) ($first['meta']['id'] ?? '')))), 'language' => 'English', 'lexemes' => $lexemes, 'attribution' => [
            'name' => $this->attribution($product)['source'],
            'text' => 'Definitions provided by '.$this->attribution($product)['source'].'.', 'license' => 'Merriam-Webster API terms', 'licenseUrl' => 'https://dictionaryapi.com/info/terms-of-service', 'sourceUrl' => $this->attribution($product)['url'], 'releaseKey' => "merriam-webster-{$product}", 'artifactKind' => 'provider', 'dumpDate' => null, 'extractorVersion' => 'provider', 'transformations' => 'Normalized by Lito Dictionary.',
        ]];
    }

    /**
     * @param  array<int, mixed>  $definitions
     * @return list<array{definitions: list<string>, examples: list<array<string, string>>}>
     */
    private function senses(array $definitions, string $product): array
    {
        $senses = [];
        foreach ($definitions as $definition) {
            $this->collectSenses($definition['sseq'] ?? [], $product, $senses);
        }

        return $senses;
    }

    /**
     * Traverse sense groups (including pseq/bs) without detaching examples from their sense.
     *
     * @param  array<mixed>  $node
     * @param  list<array{definitions: list<string>, examples: list<array<string, string>>}>  $senses
     */
    private function collectSenses(array $node, string $product, array &$senses): void
    {
        if (($node[0] ?? null) === 'sense' && is_array($node[1] ?? null)) {
            $senses[] = $this->sense($node[1], $product);
            if (is_array($node[1]['sdsense'] ?? null)) {
                $senses[] = $this->sense($node[1]['sdsense'], $product);
            }

            return;
        }
        if (is_array($node['sense'] ?? null)) {
            $this->collectSenses(['sense', $node['sense']], $product, $senses);

            return;
        }
        foreach ($node as $child) {
            if (is_array($child)) {
                $this->collectSenses($child, $product, $senses);
            }
        }
    }

    /**
     * @param  array<string, mixed>  $sense
     * @return array{definitions: list<string>, examples: list<array<string, string>>}
     */
    private function sense(array $sense, string $product): array
    {
        $texts = [];
        $examples = [];
        foreach ($sense['dt'] ?? [] as $detail) {
            if (($detail[0] ?? null) === 'text' && is_string($detail[1] ?? null)) {
                $text = $this->plainText($detail[1]);
                if ($text !== '') {
                    $texts[] = $text;
                }
            }
            if (($detail[0] ?? null) === 'vis' && is_array($detail[1] ?? null)) {
                foreach ($detail[1] as $example) {
                    // External quotations have separate rights; include provider-authored examples only.
                    if (! is_string($example['t'] ?? null) || isset($example['aq'])) {
                        continue;
                    }
                    $text = $this->plainText($example['t']);
                    if ($text !== '') {
                        $examples[] = ['text' => $text, 'attribution' => $this->attribution($product)['source'], 'license' => 'Merriam-Webster API terms', 'licenseUrl' => $this->attribution($product)['terms']];
                    }
                }
            }
        }

        return ['definitions' => $texts, 'examples' => $examples];
    }

    /**
     * @param  array<int, mixed>  $pronunciations
     * @return list<array{text: string, notation: string, audio: null}>
     */
    private function pronunciations(array $pronunciations): array
    {
        $result = [];
        foreach ($pronunciations as $pronunciation) {
            foreach (['ipa', 'mw', 'wod'] as $notation) {
                if (is_string($pronunciation[$notation] ?? null) && trim($pronunciation[$notation]) !== '') {
                    $result[] = ['text' => trim($pronunciation[$notation]), 'notation' => $notation, 'audio' => null];
                    break;
                }
            }
        }

        return $result;
    }

    private function plainText(string $text): string
    {
        // Keep the visible word in cross-reference tokens; render formatting as plain text.
        $text = preg_replace('/\{(?:a_link|d_link|dxt|et_link|i_link|mat|sx)\|([^|}]+)[^}]*\}/', '$1', $text) ?? $text;
        $text = str_replace(['{bc}', '{ldquo}', '{rdquo}', '{p_br}'], [': ', '“', '”', ' '], $text);

        return ltrim(trim(preg_replace('/\{[^}]+\}/', '', $text) ?? $text), ': ');
    }

    /** @return array{source: string, url: string, terms: string} */
    private function attribution(string $product = 'learners'): array
    {
        $title = $product === 'intermediate' ? 'Intermediate' : "Learner's";

        return ['source' => "Merriam-Webster's {$title} Dictionary", 'url' => "https://www.dictionaryapi.com/products/api-{$product}-dictionary", 'terms' => 'https://dictionaryapi.com/info/terms-of-service'];
    }
}
