<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name'])]
class PartOfSpeech extends Model
{
    protected $table = 'dictionary_parts_of_speech';
}
