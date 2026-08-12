<?php

namespace App\Support;

class RuntimeConfigurationValidator
{
    public function validate(array $configuration): array
    {
        $errors = [];

        if (! is_string($configuration['app_key'] ?? null) || $configuration['app_key'] === '') {
            $errors['app_key'] = 'The application key must be configured.';
        }

        if (($configuration['database_default'] ?? null) !== 'pgsql') {
            $errors['database'] = 'The default database connection must be pgsql.';
        }

        if (($configuration['cache_default'] ?? null) !== 'redis') {
            $errors['cache'] = 'The default cache store must be redis.';
        }

        if (($configuration['queue_default'] ?? null) !== 'redis') {
            $errors['queue'] = 'The default queue connection must be redis.';
        }

        if (($configuration['filesystem_default'] ?? null) !== 'minio') {
            $errors['filesystem'] = 'The default filesystem disk must be minio.';
        }

        $minio = $configuration['minio'] ?? [];

        foreach (['driver' => 's3', 'visibility' => 'private', 'throw' => true, 'report' => true] as $key => $value) {
            if (($minio[$key] ?? null) !== $value) {
                $errors['minio.'.$key] = sprintf('The MinIO disk %s must be %s.', $key, var_export($value, true));
            }
        }

        foreach (['key', 'secret', 'region', 'bucket', 'endpoint'] as $key) {
            if (! is_string($minio[$key] ?? null) || $minio[$key] === '') {
                $errors['minio.'.$key] = sprintf('The MinIO disk requires a %s value.', $key);
            }
        }

        if (($minio['use_path_style_endpoint'] ?? null) !== true) {
            $errors['minio.use_path_style_endpoint'] = 'The MinIO disk must use path-style endpoints.';
        }

        return $errors;
    }
}
