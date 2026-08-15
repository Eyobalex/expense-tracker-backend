<?php

return [
    'enabled' => (bool) env('API_DOCS_ENABLED', env('APP_ENV') !== 'production'),
];
