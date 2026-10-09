<?php

use App\Models\Admin;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;

test('the single-role migration rejects existing duplicates without deleting assignments', function () {
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => ':memory:',
    ]);
    DB::purge('sqlite');

    $this->artisan('migrate', [
        '--database' => 'sqlite',
        '--path' => [
            'database/migrations/2026_10_08_000000_create_admins_table.php',
            'database/migrations/2026_10_08_160909_create_permission_tables.php',
        ],
        '--force' => true,
    ])->assertSuccessful();

    $admin = Admin::create([
        'name' => 'Test Admin',
        'email' => 'admin@example.com',
        'password' => 'test-password',
    ]);
    $first = Role::create(['name' => 'editor', 'guard_name' => 'admin']);
    $second = Role::create(['name' => 'reviewer', 'guard_name' => 'admin']);
    $admin->assignRole($first, $second);

    $migration = require database_path('migrations/2026_10_08_170000_limit_models_to_one_role.php');

    expect(fn () => $migration->up())->toThrow(RuntimeException::class, 'multiple role assignments exist');

    expect($admin->fresh()->roles->modelKeys())->toEqualCanonicalizing([$first->id, $second->id])
        ->and(Schema::hasIndex('model_has_roles', 'model_has_roles_one_role_per_model_unique'))->toBeFalse();
    $this->assertDatabaseCount('model_has_roles', 2);
});
