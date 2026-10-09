<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $metadata
 */
#[Fillable(['lexeme_id', 'source_path', 'form', 'form_hash', 'metadata'])]
class Form extends Model
{
    protected $table = 'dictionary_forms';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Lexeme, $this> */
    public function lexeme(): BelongsTo
    {
        return $this->belongsTo(Lexeme::class);
    }
}
