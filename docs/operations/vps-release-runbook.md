# VPS Release Runbook

This runbook is for the Laravel API, PostgreSQL, Redis, MinIO, queue workers, scheduler, and PaddleOCR service. It does not authorize deployment by itself; it defines the checks required before production traffic.

## Network and process boundary

- Terminate TLS at Nginx and expose only ports 80/443 publicly.
- Bind PostgreSQL, Redis, MinIO, and PaddleOCR to private interfaces only. Do not expose their administration or data ports to the public internet.
- Run PHP-FPM, queue workers, scheduler, and OCR under dedicated non-root service accounts with process supervision and automatic restart.
- Store application secrets, MinIO credentials, FCM credentials, and backup encryption material outside the repository. `BACKUP_ENCRYPTION_KEY_REFERENCE` must point to the secret-manager entry or protected mounted key location, never the key material.
- Configure log rotation and protect logs because correlation identifiers and operational metadata may be present.

## Required production environment values

Set every release-policy variable in the deployed `.env` and use approved positive integer values:

- Retention: `REPORT_RETENTION_DAYS`, `FULL_ACCOUNT_EXPORT_RETENTION_DAYS`, `FAILED_OCR_ARTIFACT_RETENTION_DAYS`, `PROCESSING_DERIVATIVE_RETENTION_DAYS`, `RECEIPT_ABANDONED_RETENTION_DAYS`, `AUDIT_EVENT_RETENTION_DAYS`, `DELETED_ACCOUNT_GRACE_PERIOD_DAYS`, `POSTGRESQL_BACKUP_RETENTION_DAYS`, `MINIO_BACKUP_RETENTION_DAYS`, `SYNC_TOMBSTONE_RETENTION_DAYS`, `FAILED_JOB_RETENTION_DAYS`.
- RPO: `RPO_POSTGRESQL_MINUTES`, `RPO_MINIO_RECEIPTS_MINUTES`, `RPO_REPORT_EXPORT_ARTIFACTS_MINUTES`.
- RTO: `RTO_CORE_API_DATABASE_MINUTES`, `RTO_RECEIPT_STORAGE_MINUTES`, `RTO_QUEUE_OCR_MINUTES`.
- Backup topology: `POSTGRESQL_BACKUP_DESTINATION`, `MINIO_BACKUP_DESTINATION`, `BACKUP_ENCRYPTION_KEY_REFERENCE`.
- Approval evidence: set `RELEASE_RETENTION_POLICY_APPROVED`, `RELEASE_RECOVERY_OBJECTIVES_APPROVED`, and `RELEASE_BACKUP_RESTORE_DRILL_COMPLETED` to `true` only after the corresponding approval or drill has occurred.

`APP_DEBUG` must be `false`. Keep `API_DOCS_ENABLED=false` unless documentation is separately protected by an approved access-control design.

## Pre-deployment sequence

1. Confirm the deployment commit is an approved merge in `dev` and production promotion is authorized by the user.
2. Install locked dependencies with Composer, run `php artisan config:cache`, `php artisan route:cache`, and `php artisan view:cache`.
3. Run `php artisan migrate --force` only after a database backup has completed successfully.
4. Run `php artisan runtime:check --production --deep`.
5. Restart PHP-FPM and supervised queue workers. Run workers for `default`, `ocr`, `reports`, and notification queues as applicable.
6. Confirm the scheduler is invoking `php artisan schedule:run` at least once per minute, or run `schedule:work` under supervision.
7. Verify the authenticated API, `/up` health endpoint, PostgreSQL, Redis, private MinIO, OCR service, worker queue processing, and scheduled commands.

## Backup and restore drill

1. Capture the start time and backup identifiers; verify encrypted PostgreSQL and MinIO backups were written to their configured private destinations.
2. Restore PostgreSQL into an isolated temporary database. Restore MinIO objects into an isolated bucket or namespace.
3. Point an isolated Laravel runtime at the restored services and run `php artisan runtime:check --deep`.
4. Verify a posted transaction, its journal lines, a budget period, a receipt original, and a private report/export artifact are readable and correctly user-scoped.
5. Verify Redis workers and the scheduler can resume without duplicating a completed report, OCR process, or notification delivery.
6. Record measured data loss and restoration duration against the approved RPO/RTO values. Set `RELEASE_BACKUP_RESTORE_DRILL_COMPLETED=true` only after the results meet those objectives.

## Failure handling

- Do not deploy if `runtime:check --production --deep` fails.
- Do not retry a failed database migration by altering production data manually. Restore or use an approved forward migration.
- Keep MinIO artifacts private; use authenticated API downloads rather than public bucket policies.
- Investigate queue, OCR, FX, report, and storage failures using sanitized correlation identifiers only. Do not put receipt images, OCR payloads, tokens, or full financial payloads in logs or incident tickets.
