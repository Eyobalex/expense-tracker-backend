<?php

namespace Tests\Feature\Console\Commands;

use Illuminate\Support\Facades\Config;
use Tests\TestCase;

class RuntimeCheckTest extends TestCase
{
    public function test_it_accepts_the_required_runtime_configuration(): void
    {
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
    }

    public function test_it_fails_when_the_runtime_configuration_is_invalid(): void
    {
        Config::set('database.default', 'sqlite');

        $this->artisan('runtime:check')
            ->expectsOutput('The default database connection must be pgsql.')
            ->assertFailed();
    }
}
