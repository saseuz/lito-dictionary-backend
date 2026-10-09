<?php

namespace App\Models\Dictionary;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'name', 'homepage_url', 'default_license_identifier', 'default_license_url', 'default_attribution_text', 'active_release_id'])]
class Source extends Model
{
    protected $table = 'dictionary_sources';

    /** @return HasMany<Release, $this> */
    public function releases(): HasMany
    {
        return $this->hasMany(Release::class);
    }

    /** @return BelongsTo<Release, $this> */
    public function activeRelease(): BelongsTo
    {
        return $this->belongsTo(Release::class, 'active_release_id');
    }
}
