<?php

namespace App\Actions\Dictionary;

use App\Contracts\DictionaryProvider;
use App\Models\DictionaryResult;
use App\Support\Dictionary\DictionaryText;

class ProviderLookup
{
    public function __construct(private DictionaryProvider $provider) {}

    /** @return array{available: bool, entry: array<string, mixed>|null, error: string|null} */
    public function handle(string $word, string $language): array
    {
        $name = (string) config('services.dictionary.provider', 'free_dictionary');
        $hash = hash('sha256', $word);
        $word = DictionaryResult::where(['provider' => $name, 'language' => $language, 'lookup_hash' => $hash])->first();
        // Reuse usable saved entries permanently. Legacy incomplete payloads
        // must still be fetched again to avoid breaking the detail page.
        $wordIsRenderable = is_array($word?->payload)
            && isset($word->payload['attribution']['license'])
            && isset($word->payload['lexemes'][0]['senses'][0]['tags']);
        if ($word && $word->status === 'hit' && $wordIsRenderable) {
            return ['available' => true, 'entry' => $word->payload, 'error' => null];
        }
        $result = $this->provider->lookup($word, $language);
        $entry = $result['entry'];
        if ($result['status'] !== 'error') {
            DictionaryResult::updateOrCreate(
                ['provider' => $name, 'language' => $language, 'lookup_hash' => $hash],
                ['lookup' => $word, 'status' => $result['status'], 'payload' => $entry, 'attribution' => json_encode($result['attribution'], JSON_THROW_ON_ERROR), 'terms_url' => $result['attribution']['terms'], 'fetched_at' => now(), 'expires_at' => null, 'response_hash' => $entry ? hash('sha256', DictionaryText::canonical($entry)) : null, 'error_message' => null]
            );
        }

        return [
            'available' => $result['status'] !== 'error',
            'entry' => $entry,
            'error' => $result['status'] === 'error' ? 'Provider failed. Please try again later.' : null,
        ];
    }
}
