<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['language_id', 'headword', 'headword_hash'])]
class Word extends Model
{
    protected $table = 'dictionary_words';

    /** @return BelongsTo<Language, $this> */
    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    /** @return HasMany<Lexeme, $this> */
    public function lexemes(): HasMany
    {
        return $this->hasMany(Lexeme::class);
    }
}
