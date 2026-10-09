<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $metadata
 */
#[Fillable(['sense_id', 'position', 'text', 'kind', 'source_reference', 'publication_status', 'license_identifier', 'license_url', 'attribution_text', 'rights_note', 'metadata'])]
class Example extends Model
{
    protected $table = 'dictionary_examples';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['metadata' => 'array'];
    }

    /** @return BelongsTo<Sense, $this> */
    public function sense(): BelongsTo
    {
        return $this->belongsTo(Sense::class);
    }
}
