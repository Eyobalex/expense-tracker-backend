<?php

return [
    /* Production retention values require product/operations approval before release. */
    'cursor_retention_days' => (int) env('SYNC_CURSOR_RETENTION_DAYS', 30),
    'tombstone_retention_days' => (int) env('SYNC_TOMBSTONE_RETENTION_DAYS', 30),
    'page_size' => (int) env('SYNC_PAGE_SIZE', 100),
    'maximum_page_size' => (int) env('SYNC_MAXIMUM_PAGE_SIZE', 250),
    'rate_limit_per_minute' => (int) env('SYNC_RATE_LIMIT_PER_MINUTE', 30),
];
