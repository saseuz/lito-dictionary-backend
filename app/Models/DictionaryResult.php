<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/** @property array<string, mixed>|null $payload */
#[Fillable(['provider', 'language', 'lookup', 'lookup_hash', 'status', 'payload', 'attribution', 'terms_url', 'fetched_at', 'expires_at', 'response_hash', 'error_message'])]
class DictionaryResult extends Model
{
    protected $table = 'dictionary_results';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['payload' => 'array', 'fetched_at' => 'immutable_datetime', 'expires_at' => 'immutable_datetime'];
    }
}
