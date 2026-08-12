<?php

namespace Tests\Unit;

use App\Support\RuntimeConfigurationValidator;
use PHPUnit\Framework\TestCase;

class InfrastructureConfigurationTest extends TestCase
{
    public function test_it_accepts_the_required_postgresql_redis_and_private_minio_configuration(): void
    {
        $errors = (new RuntimeConfigurationValidator)->validate([
            'app_key' => 'base64:test-key',
            'database_default' => 'pgsql',
            'cache_default' => 'redis',
            'queue_default' => 'redis',
            'filesystem_default' => 'minio',
            'minio' => [
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
            ],
        ]);

        $this->assertSame([], $errors);
    }

    public function test_it_reports_each_invalid_runtime_default(): void
    {
        $errors = (new RuntimeConfigurationValidator)->validate([
            'database_default' => 'sqlite',
            'cache_default' => 'database',
            'queue_default' => 'database',
            'filesystem_default' => 'local',
            'minio' => [],
        ]);

        $this->assertArrayHasKey('app_key', $errors);
        $this->assertArrayHasKey('database', $errors);
        $this->assertArrayHasKey('cache', $errors);
        $this->assertArrayHasKey('queue', $errors);
        $this->assertArrayHasKey('filesystem', $errors);
        $this->assertArrayHasKey('minio.driver', $errors);
        $this->assertArrayHasKey('minio.visibility', $errors);
        $this->assertArrayHasKey('minio.key', $errors);
        $this->assertArrayHasKey('minio.use_path_style_endpoint', $errors);
    }
}
