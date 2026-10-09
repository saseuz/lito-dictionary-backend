<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name'])]
class Language extends Model
{
    protected $table = 'dictionary_languages';

    /** @return HasMany<Word, $this> */
    public function words(): HasMany
    {
        return $this->hasMany(Word::class);
    }
}
