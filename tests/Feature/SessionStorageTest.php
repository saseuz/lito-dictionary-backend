<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

beforeEach(function () {
    // Start with the original schema in a fresh, isolated connection.
    config([
        'database.default' => 'sqlite',
        'database.connections.sqlite.url' => null,
        'database.connections.sqlite.database' => ':memory:',
        'session.driver' => 'array',
    ]);
    DB::purge('sqlite');

    $this->artisan('migrate', [
        '--database' => 'sqlite',
        '--path' => 'database/migrations/0001_01_01_000000_create_users_table.php',
        '--force' => true,
    ])->assertSuccessful();

    DB::table('sessions')->insert([
        [
            'id' => 'existing-user-session',
            'user_id' => 42,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Existing browser',
            'payload' => base64_encode('existing-session-payload'),
            'last_activity' => 1700000000,
        ],
        [
            'id' => 'existing-guest-session',
            'user_id' => null,
            'ip_address' => null,
            'user_agent' => null,
            'payload' => base64_encode('guest-session-payload'),
            'last_activity' => 1700000000,
        ],
    ]);

    $this->artisan('migrate', [
        '--database' => 'sqlite',
        '--path' => 'database/migrations/2026_10_08_000001_make_session_user_ids_strings.php',
        '--force' => true,
    ])->assertSuccessful();
});

test('session account IDs use a nullable string column with the existing index and no foreign key', function () {
    $column = collect(Schema::getColumns('sessions'))->firstWhere('name', 'user_id');

    expect($column['type_name'])->toBeIn(['varchar', 'text'])
        ->and($column['nullable'])->toBeTrue()
        ->and(Schema::hasIndex('sessions', 'sessions_user_id_index'))->toBeTrue()
        ->and(Schema::hasIndex('sessions', ['user_id']))->toBeTrue()
        ->and(Schema::getForeignKeys('sessions'))->toBeEmpty();
});

test('existing numeric account IDs and session records survive the migration', function () {
    $session = DB::table('sessions')->find('existing-user-session');

    expect($session->user_id)->toBe('42')
        ->and($session->ip_address)->toBe('127.0.0.1')
        ->and($session->user_agent)->toBe('Existing browser')
        ->and($session->payload)->toBe(base64_encode('existing-session-payload'))
        ->and($session->last_activity)->toBe(1700000000)
        ->and(DB::table('sessions')->count())->toBe(2);
});

test('UUID account IDs can be stored and retrieved in sessions', function () {
    $accountId = (string) Str::uuid();

    DB::table('sessions')->insert([
        'id' => 'admin-session',
        'user_id' => $accountId,
        'payload' => base64_encode('admin-session-payload'),
        'last_activity' => time(),
    ]);

    expect(DB::table('sessions')->find('admin-session')->user_id)->toBe($accountId);
});

test('guest sessions retain nullable account IDs before and after the migration', function () {
    $existing = DB::table('sessions')->find('existing-guest-session');

    expect($existing->user_id)->toBeNull()
        ->and($existing->payload)->toBe(base64_encode('guest-session-payload'));

    DB::table('sessions')->insert([
        'id' => 'new-guest-session',
        'user_id' => null,
        'payload' => base64_encode('new-guest-payload'),
        'last_activity' => time(),
    ]);

    $this->assertDatabaseHas('sessions', [
        'id' => 'new-guest-session',
        'user_id' => null,
    ]);
});
