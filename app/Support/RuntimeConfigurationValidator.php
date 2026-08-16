<?php

namespace App\Support;

class RuntimeConfigurationValidator
{
    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, string>
     */
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

    /**
     * @param  array<string, mixed>  $configuration
     * @return array<string, string>
     */
    public function validateProductionRelease(array $configuration): array
    {
        $errors = [];

        if (($configuration['app_debug'] ?? null) !== false) {
            $errors['app_debug'] = 'Production release requires APP_DEBUG=false.';
        }

        if (($configuration['api_docs_enabled'] ?? null) !== false) {
            $errors['api_docs'] = 'Production release requires API documentation access to be disabled unless explicitly protected.';
        }

        $release = $configuration['release'] ?? [];
        if (! is_array($release)) {
            return ['release' => 'Production release configuration is missing.'];
        }

        foreach ([
            'retention_policy_approved' => 'Approved concrete retention policy values are required before production release.',
            'recovery_objectives_approved' => 'Approved RPO/RTO objectives are required before production release.',
            'backup_restore_drill_completed' => 'A successful backup and restore drill is required before production release.',
        ] as $key => $message) {
            if (($release[$key] ?? null) !== true) {
                $errors['release.'.$key] = $message;
            }
        }

        foreach ([
            'retention_days' => ['generated_reports', 'full_account_exports', 'failed_ocr_artifacts', 'processing_derivatives', 'abandoned_receipts', 'audit_events', 'deleted_account_grace', 'postgresql_backups', 'minio_backups', 'sync_tombstones', 'failed_jobs'],
            'rpo_minutes' => ['postgresql', 'minio_receipts', 'report_export_artifacts'],
            'rto_minutes' => ['core_api_database', 'receipt_storage', 'queue_ocr'],
        ] as $group => $requiredKeys) {
            $values = $release[$group] ?? [];
            if (! is_array($values)) {
                $errors['release.'.$group] = sprintf('Production release requires configured %s.', str_replace('_', ' ', $group));

                continue;
            }

            foreach ($requiredKeys as $key) {
                $value = $values[$key] ?? null;
                if (filter_var($value, FILTER_VALIDATE_INT) === false || (int) $value <= 0) {
                    $errors['release.'.$group.'.'.$key] = sprintf('Production release requires a positive value for %s.%s.', $group, $key);
                }
            }
        }

        return $errors;
    }
}
