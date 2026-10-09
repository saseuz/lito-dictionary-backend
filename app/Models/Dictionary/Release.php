<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property array<string, mixed> $manifest
 */
#[Fillable(['source_id', 'release_key', 'artifact_sha256', 'manifest_sha256', 'adapter_version', 'scope_key', 'artifact_kind', 'source_url', 'upstream_dump_date', 'extracted_at', 'extractor_version', 'license_identifier', 'license_url', 'attribution_text', 'manifest', 'status', 'ready_at', 'content_sha256'])]
class Release extends Model
{
    protected $table = 'dictionary_releases';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['manifest' => 'array', 'ready_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<Source, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(Source::class);
    }

    /** @return HasMany<Lexeme, $this> */
    public function lexemes(): HasMany
    {
        return $this->hasMany(Lexeme::class);
    }

    /** @return HasMany<ImportRun, $this> */
    public function runs(): HasMany
    {
        return $this->hasMany(ImportRun::class, 'release_id');
    }
}
