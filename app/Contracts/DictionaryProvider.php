<?php

namespace App\Contracts;

interface DictionaryProvider
{
    /** @return array{status: string, entry: array<string, mixed>|null, attribution: array<string, string>} */
    public function lookup(string $word, string $language): array;
}
