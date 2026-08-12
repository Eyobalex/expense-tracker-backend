<?php

use Illuminate\Support\Facades\Config;

test('it accepts the required runtime configuration', function (): void {
    Config::set('app.key', 'base64:test-key');
    Config::set('database.default', 'pgsql');
    Config::set('cache.default', 'redis');
    Config::set('queue.default', 'redis');
    Config::set('filesystems.default', 'minio');
    Config::set('filesystems.disks.minio', [
        'driver' => 's3',
        'key' => 'access-key',
        'secret' => 'secret-key',
        'bucket' => 'expense-tracker',
        'region' => 'us-east-1',
        'endpoint' => 'http://minio:9000',
        'visibility' => 'private',
        'throw' => true,
        'report' => true,
        'use_path_style_endpoint' => true,
    ]);

    $this->artisan('runtime:check')
        ->expectsOutput('Runtime configuration is valid.')
        ->assertSuccessful();
});

test('it fails when the runtime configuration is invalid', function (): void {
    Config::set('database.default', 'sqlite');

    $this->artisan('runtime:check')
        ->expectsOutput('The default database connection must be pgsql.')
        ->assertFailed();
});
