<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property array<string, mixed> $context
 */
#[Fillable(['import_run_id', 'line_number', 'source_record_hash', 'code', 'message', 'context', 'created_at'])]
class ImportError extends Model
{
    protected $table = 'dictionary_import_errors';

    public $timestamps = false;

    /** @return array<string, string> */
    protected function casts(): array
    {
        return ['context' => 'array', 'created_at' => 'immutable_datetime'];
    }

    /** @return BelongsTo<ImportRun, $this> */
    public function run(): BelongsTo
    {
        return $this->belongsTo(ImportRun::class, 'import_run_id');
    }
}
