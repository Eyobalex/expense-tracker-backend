<?php

return [
    'disk' => env('REPORTS_DISK', env('RECEIPTS_DISK', 'minio')),
    'retention_days' => (int) env('REPORT_RETENTION_DAYS', 7),
    'full_account_export_retention_days' => (int) env('FULL_ACCOUNT_EXPORT_RETENTION_DAYS', 3),
    'allow_synchronous_csv' => env('REPORTS_ALLOW_SYNCHRONOUS_CSV', true),
    'synchronous_csv_row_limit' => (int) env('REPORTS_SYNCHRONOUS_CSV_ROW_LIMIT', 10000),
    'rate_limit_per_minute' => (int) env('REPORTS_RATE_LIMIT_PER_MINUTE', 10),
];
