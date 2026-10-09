<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['release_id', 'status', 'started_at', 'finished_at', 'records_seen', 'records_inserted', 'records_unchanged', 'records_skipped', 'records_rejected', 'summary'])]
class ImportRun extends Model
{
    protected $table = 'dictionary_import_runs';

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'started_at' => 'immutable_datetime',
            'finished_at' => 'immutable_datetime',
            'records_seen' => 'integer',
            'records_inserted' => 'integer',
            'records_unchanged' => 'integer',
            'records_skipped' => 'integer',
            'records_rejected' => 'integer',
        ];
    }

    /** @return BelongsTo<Release, $this> */
    public function release(): BelongsTo
    {
        return $this->belongsTo(Release::class);
    }

    /** @return HasMany<ImportError, $this> */
    public function errors(): HasMany
    {
        return $this->hasMany(ImportError::class, 'import_run_id');
    }
}
