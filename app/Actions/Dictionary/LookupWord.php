<?php

namespace App\Actions\Dictionary;

class LookupWord
{
    public function __construct(private ProviderLookup $providerLookup) {}

    /** @return array{available: bool, entry: array<string, mixed>|null, error: string|null, origin?: string} */
    public function handle(?string $spelling, string $language = 'en'): array
    {
        if ($spelling === null) {
            return ['available' => true, 'entry' => null, 'error' => null];
        }

        return $this->providerLookup->handle($spelling, $language);
    }
}
