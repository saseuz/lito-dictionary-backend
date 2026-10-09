<?php

namespace App\Actions\Dictionary;

use App\Models\Dictionary\Release;
use App\Models\Dictionary\Source;
use App\Support\Dictionary\ReleaseIntegrity;
use App\Support\Dictionary\SourceLock;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ActivateDictionaryRelease
{
    public function __construct(private ReleaseIntegrity $integrity) {}

    public function handle(Source $source, Release $release): void
    {
        DB::transaction(function () use ($source, $release): void {
            $locked = SourceLock::acquire($source->id);
            $release->refresh();
            if ($release->source_id !== $locked->id || $release->status !== 'ready' || ! $release->runs()->where('status', 'completed')->where('records_rejected', 0)->whereDoesntHave('errors')->exists()) {
                throw new InvalidArgumentException('Only a complete, ready release belonging to this source can be activated.');
            }
            if ($release->content_sha256 !== $this->integrity->hash($release)) {
                throw new InvalidArgumentException('Release integrity check failed; activation refused.');
            }
            if ($release->artifact_kind === 'fixture' && (! app()->environment(['local', 'testing']) || $locked->activeRelease?->artifact_kind === 'snapshot')) {
                throw new InvalidArgumentException('Fixtures are for local/testing sources and cannot replace an active snapshot.');
            }
            $locked->update(['active_release_id' => $release->id]);
            $source->refresh();
        });
    }
}
