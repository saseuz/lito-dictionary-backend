<?php

namespace Tests\Concerns;

use Illuminate\Support\Facades\DB;

trait UsesIsolatedDatabase
{
    public function setUpUsesIsolatedDatabase(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.url' => null,
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');

        $this->artisan('migrate', ['--database' => 'sqlite', '--force' => true])
            ->assertSuccessful();
    }
}
