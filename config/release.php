<?php

return [
    'retention_policy_approved' => (bool) env('RELEASE_RETENTION_POLICY_APPROVED', false),
    'recovery_objectives_approved' => (bool) env('RELEASE_RECOVERY_OBJECTIVES_APPROVED', false),
    'backup_restore_drill_completed' => (bool) env('RELEASE_BACKUP_RESTORE_DRILL_COMPLETED', false),
    'retention_days' => [
        'generated_reports' => env('REPORT_RETENTION_DAYS'),
        'full_account_exports' => env('FULL_ACCOUNT_EXPORT_RETENTION_DAYS'),
        'failed_ocr_artifacts' => env('FAILED_OCR_ARTIFACT_RETENTION_DAYS'),
        'processing_derivatives' => env('PROCESSING_DERIVATIVE_RETENTION_DAYS'),
        'abandoned_receipts' => env('RECEIPT_ABANDONED_RETENTION_DAYS'),
        'audit_events' => env('AUDIT_EVENT_RETENTION_DAYS'),
        'deleted_account_grace' => env('DELETED_ACCOUNT_GRACE_PERIOD_DAYS'),
        'postgresql_backups' => env('POSTGRESQL_BACKUP_RETENTION_DAYS'),
        'minio_backups' => env('MINIO_BACKUP_RETENTION_DAYS'),
        'sync_tombstones' => env('SYNC_TOMBSTONE_RETENTION_DAYS'),
        'failed_jobs' => env('FAILED_JOB_RETENTION_DAYS'),
    ],
    'rpo_minutes' => [
        'postgresql' => env('RPO_POSTGRESQL_MINUTES'),
        'minio_receipts' => env('RPO_MINIO_RECEIPTS_MINUTES'),
        'report_export_artifacts' => env('RPO_REPORT_EXPORT_ARTIFACTS_MINUTES'),
    ],
    'rto_minutes' => [
        'core_api_database' => env('RTO_CORE_API_DATABASE_MINUTES'),
        'receipt_storage' => env('RTO_RECEIPT_STORAGE_MINUTES'),
        'queue_ocr' => env('RTO_QUEUE_OCR_MINUTES'),
    ],
];
