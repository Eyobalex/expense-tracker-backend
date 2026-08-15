<?php

return [
    'provider' => 'open_exchange_rates',
    'base_currency' => 'USD',
    'quote_currency' => 'ETB',
    'refresh_time' => '00:30',
    // The Open Exchange Rates Free plan allows 1,000 requests per month. The
    // scheduled daily refresh consumes at most one logical provider request.
    'monthly_quota' => (int) env('OPEN_EXCHANGE_RATES_MONTHLY_QUOTA', 1000),
    'alert_after_hours' => 24,
    'maximum_staleness_hours' => 48,
    'http' => [
        'base_url' => env('OPEN_EXCHANGE_RATES_URL', 'https://openexchangerates.org/api'),
        'connect_timeout' => (int) env('OPEN_EXCHANGE_RATES_CONNECT_TIMEOUT', 5),
        'timeout' => (int) env('OPEN_EXCHANGE_RATES_TIMEOUT', 15),
    ],
];
