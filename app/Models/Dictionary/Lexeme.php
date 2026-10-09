<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $raw_payload
 */
#[Fillable(['word_id', 'release_id', 'part_of_speech_id', 'source_record_hash', 'source_line_number', 'source_page_title', 'source_page_url', 'source_pos', 'etymology_key', 'etymology_text', 'raw_payload'])]
class Lexeme extends Model
{
    protected $table = 'dictionary_lexemes';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['raw_payload' => 'array'];
    }

    /** @return BelongsTo<Word, $this> */
    public function word(): BelongsTo
    {
        return $this->belongsTo(Word::class);
    }

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return BelongsTo<PartOfSpeech, $this> */
    public function partOfSpeech(): BelongsTo
    {
        return $this->belongsTo(PartOfSpeech::class);
    }

    /** @return HasMany<Sense, $this> */
    public function senses(): HasMany
    {
        return $this->hasMany(Sense::class)->orderBy('position');
    }

    /** @return HasMany<Pronunciation, $this> */
    public function pronunciations(): HasMany
    {
        return $this->hasMany(Pronunciation::class);
    }

    /** @return HasMany<Form, $this> */
    public function forms(): HasMany
    {
        return $this->hasMany(Form::class);
    }

    /** @return HasMany<SemanticRelation, $this> */
    public function relations(): HasMany
    {
        return $this->hasMany(SemanticRelation::class, 'lexeme_id');
    }
}
