<?php

namespace App\Services\Dictionary;

use App\Contracts\DictionaryProvider;

/** Original Lito demonstration text, dedicated to CC0-1.0; no external definitions. */
class PublicFixtureProvider implements DictionaryProvider
{
    public function lookup(string $word, string $language): array
    {
        $attribution = ['source' => 'Lito demo dictionary', 'url' => 'https://creativecommons.org/publicdomain/zero/1.0/', 'terms' => 'https://creativecommons.org/publicdomain/zero/1.0/'];
        if ($language !== 'en' || $word !== 'hello') {
            return ['status' => 'miss', 'entry' => null, 'attribution' => $attribution];
        }

        return ['status' => 'hit', 'attribution' => $attribution, 'entry' => [
            'headword' => 'hello', 'language' => 'English',
            'lexemes' => [[
                'partOfSpeech' => 'interjection',
                'pronunciations' => [['notation' => 'ipa', 'text' => '/həˈloʊ/', 'audio' => null]],
                'senses' => [[
                    'tags' => [], 'definitions' => ['A friendly word used to greet someone or begin a conversation.'],
                    'examples' => [['text' => 'Hello, Mira! Shall we read together?', 'attribution' => 'Lito demo dictionary', 'license' => 'CC0-1.0', 'licenseUrl' => $attribution['terms']]],
                ]],
            ]],
            'attribution' => ['name' => $attribution['source'], 'text' => 'Original Lito demonstration content, dedicated to CC0-1.0.', 'license' => 'CC0-1.0', 'licenseUrl' => $attribution['terms'], 'sourceUrl' => $attribution['url']],
        ]];
    }
}
