<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $metadata
 */
#[Fillable(['lexeme_id', 'position', 'metadata'])]
class Sense extends Model
{
    protected $table = 'dictionary_senses';

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

    /** @return HasMany<Definition, $this> */
    public function definitions(): HasMany
    {
        return $this->hasMany(Definition::class)->orderBy('position');
    }

    /** @return HasMany<Example, $this> */
    public function examples(): HasMany
    {
        return $this->hasMany(Example::class)->orderBy('position');
    }

    /** @return HasMany<SemanticRelation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(SemanticRelation::class, 'sense_id');
    }
}
