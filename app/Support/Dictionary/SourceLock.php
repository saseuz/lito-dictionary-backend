<?php

namespace App\Support\Dictionary;

use App\Models\Dictionary\Source;
use Illuminate\Support\Facades\DB;

class SourceLock
{
    /** Call only inside a transaction. */
    public static function acquire(int $sourceId): Source
    {
        if (DB::connection()->getDriverName() === 'sqlite') {
            DB::table('dictionary_sources')->where('id', $sourceId)->update(['key' => DB::raw('key')]);
        }

        return Source::query()->lockForUpdate()->findOrFail($sourceId);
    }
}
