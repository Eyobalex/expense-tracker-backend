<?php

return [
    'disk' => env('RECEIPT_FILESYSTEM_DISK', 'minio'),
    'max_bytes' => (int) env('RECEIPT_MAX_UPLOAD_BYTES', 15 * 1024 * 1024),
    'max_pixels' => (int) env('RECEIPT_MAX_PIXELS', 24_000_000),
    'allowed_mime_types' => ['image/jpeg', 'image/png', 'image/webp'],
    'preprocessing_version' => 'v1',
    'parser' => [
        'version' => 'locale-matrix-v1',
        'languages' => ['en', 'am'],
        'currencies' => ['ETB', 'USD'],
        'date_formats' => ['Y-m-d', 'd M Y', 'M d Y'],
        'timezone_interpretation' => 'date_only_pending_confirmation',
    ],
    'abandoned_retention_days' => (int) env('RECEIPT_ABANDONED_RETENTION_DAYS', 30),
    'ocr' => [
        'url' => env('PADDLE_OCR_URL', 'http://ocr:8000'),
        'connect_timeout' => (int) env('PADDLE_OCR_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('PADDLE_OCR_TIMEOUT', 45),
        'provider_version' => env('PADDLE_OCR_PROVIDER_VERSION', 'paddleocr'),
        'model_version' => env('PADDLE_OCR_MODEL_VERSION', 'pp-ocrv6'),
    ],
];
