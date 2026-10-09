<?php

namespace App\Actions\Dictionary;

use App\Contracts\DictionaryProvider;
use App\Models\DictionaryResult;
use App\Support\Dictionary\DictionaryText;

class ProviderLookup
{
    public function __construct(private DictionaryProvider $provider, private ?string $identity = null, private bool $persist = true) {}

    /** @return array{available: bool, entry: array<string, mixed>|null, error: string|null, origin: string} */
    public function handle(string $word, string $language): array
    {
        $word = DictionaryText::spelling($word);
        $language = strtolower(trim($language));
        $name = $this->identity ?? (string) config('services.dictionary.provider', 'free_dictionary');
        $hash = hash('sha256', $word);
        $saved = $this->persist ? DictionaryResult::where(['provider' => $name, 'language' => $language, 'lookup_hash' => $hash])->first() : null;
        // Reuse usable saved entries permanently. Legacy incomplete payloads
        // must still be fetched again to avoid breaking the detail page.
        $wordIsRenderable = is_array($saved?->payload)
            && isset($saved->payload['attribution']['license'])
            && isset($saved->payload['lexemes'][0]['senses'][0]['tags']);
        if ($saved && $saved->status === 'hit' && $wordIsRenderable) {
            return ['available' => true, 'entry' => $saved->payload, 'error' => null, 'origin' => 'local'];
        }
        $result = $this->provider->lookup($word, $language);
        $entry = $result['entry'];
        if ($this->persist && $result['status'] !== 'error') {
            DictionaryResult::updateOrCreate(
                ['provider' => $name, 'language' => $language, 'lookup_hash' => $hash],
                ['lookup' => $word, 'status' => $result['status'], 'payload' => $entry, 'attribution' => json_encode($result['attribution'], JSON_THROW_ON_ERROR), 'terms_url' => $result['attribution']['terms'], 'fetched_at' => now(), 'expires_at' => null, 'response_hash' => $entry ? hash('sha256', DictionaryText::canonical($entry)) : null, 'error_message' => null]
            );
        }

        return [
            'available' => $result['status'] !== 'error',
            'entry' => $entry,
            'error' => $result['status'] === 'error' ? 'Provider failed. Please try again later.' : null,
            'origin' => 'provider',
        ];
    }
}
