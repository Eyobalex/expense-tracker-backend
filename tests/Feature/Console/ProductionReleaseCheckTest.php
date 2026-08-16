<?php

use Illuminate\Support\Facades\Config;

function validProductionReleaseConfiguration(): array
{
    return [
        'retention_policy_approved' => true,
        'recovery_objectives_approved' => true,
        'backup_restore_drill_completed' => true,
        'backups' => [
            'postgresql_destination' => 's3://private-backups/postgresql',
            'minio_destination' => 's3://private-backups/minio',
            'encryption_key_reference' => 'secret://production/backup-key',
        ],
        'retention_days' => array_fill_keys(['generated_reports', 'full_account_exports', 'failed_ocr_artifacts', 'processing_derivatives', 'abandoned_receipts', 'audit_events', 'deleted_account_grace', 'postgresql_backups', 'minio_backups', 'sync_tombstones', 'failed_jobs'], 30),
        'rpo_minutes' => array_fill_keys(['postgresql', 'minio_receipts', 'report_export_artifacts'], 60),
        'rto_minutes' => array_fill_keys(['core_api_database', 'receipt_storage', 'queue_ocr'], 120),
    ];
}

test('production runtime checks require explicit approved retention, recovery, and restore-drill gates', function (): void {
    Config::set('app.key', 'base64:test-key');
    Config::set('app.debug', false);
    Config::set('api_docs.enabled', false);
    Config::set('database.default', 'pgsql');
    Config::set('cache.default', 'redis');
    Config::set('queue.default', 'redis');
    Config::set('filesystems.default', 'minio');
    Config::set('filesystems.disks.minio', [
        'driver' => 's3', 'key' => 'access-key', 'secret' => 'secret-key', 'bucket' => 'expense-tracker', 'region' => 'us-east-1',
        'endpoint' => 'http://minio:9000', 'visibility' => 'private', 'throw' => true, 'report' => true, 'use_path_style_endpoint' => true,
    ]);
    Config::set('release', validProductionReleaseConfiguration());

    $this->artisan('runtime:check --production')
        ->expectsOutput('Runtime configuration is valid.')
        ->assertSuccessful();
});

test('production runtime checks fail closed for missing approvals or recovery objectives', function (): void {
    Config::set('app.debug', true);
    Config::set('api_docs.enabled', true);
    Config::set('release', ['retention_policy_approved' => false, 'recovery_objectives_approved' => false, 'backup_restore_drill_completed' => false]);

    $this->artisan('runtime:check --production')
        ->expectsOutput('Production release requires APP_DEBUG=false.')
        ->expectsOutput('Approved concrete retention policy values are required before production release.')
        ->expectsOutput('Production release requires release.backups.postgresql_destination.')
        ->expectsOutput('Production release requires a positive value for retention_days.generated_reports.')
        ->assertFailed();
});
