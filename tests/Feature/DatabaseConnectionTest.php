<?php

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

test('default database connection is pgsql', function () {
    expect(config('database.default'))->toBe('pgsql');
});

test('pgsql connection configuration is complete', function () {
    $pgsqlConfig = config('database.connections.pgsql');

    expect($pgsqlConfig)
        ->toBeArray()
        ->and($pgsqlConfig['driver'])->toBe('pgsql')
        ->and($pgsqlConfig['host'])->toBe('127.0.0.1')
        ->and($pgsqlConfig['port'])->toBe('5432');
});

test('database connection is active and uses PostgreSQL', function () {
    $pdo = DB::connection()->getPdo();

    expect($pdo)->toBeInstanceOf(PDO::class);
    expect($pdo->getAttribute(PDO::ATTR_DRIVER_NAME))->toBe('pgsql');
});

test('database name matches configured value', function () {
    $databaseName = DB::connection()->getDatabaseName();

    expect($databaseName)->toBe('dancymeals_test');
});

test('default migration tables exist', function () {
    expect(Schema::hasTable('users'))->toBeTrue();
    expect(Schema::hasTable('password_reset_tokens'))->toBeTrue();
    expect(Schema::hasTable('sessions'))->toBeTrue();
    expect(Schema::hasTable('cache'))->toBeTrue();
    expect(Schema::hasTable('cache_locks'))->toBeTrue();
    expect(Schema::hasTable('jobs'))->toBeTrue();
    expect(Schema::hasTable('job_batches'))->toBeTrue();
    expect(Schema::hasTable('failed_jobs'))->toBeTrue();
});

test('users table has expected columns', function () {
    $columns = Schema::getColumnListing('users');

    expect($columns)
        ->toContain('id')
        ->toContain('name')
        ->toContain('email')
        ->toContain('password');
});

test('database credentials are not hardcoded in config', function () {
    $configContent = file_get_contents(base_path('config/database.php'));

    expect($configContent)
        ->toContain("env('DB_HOST'")
        ->toContain("env('DB_PORT'")
        ->toContain("env('DB_DATABASE'")
        ->toContain("env('DB_USERNAME'")
        ->toContain("env('DB_PASSWORD'");
});

test('can perform basic database operations', function () {
    $result = DB::select('SELECT current_database() as db_name');

    expect($result)->toBeArray()
        ->and($result[0]->db_name)->toBe('dancymeals_test');
});
