<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $metadata
 */
#[Fillable(['lexeme_id', 'sense_id', 'source_path', 'kind', 'target_language_id', 'target_text', 'target_hash', 'target_word_id', 'metadata'])]
class SemanticRelation extends Model
{
    protected $table = 'dictionary_relations';

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

    /** @return BelongsTo<Sense, $this> */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }

    /** @return BelongsTo<Word, $this> */
    public function targetWord(): BelongsTo
    {
        return $this->belongsTo(Word::class, 'target_word_id');
    }

    /** @return BelongsTo<Language, $this> */
    public function targetLanguage(): BelongsTo
    {
        return $this->belongsTo(Language::class, 'target_language_id');
    }
}
