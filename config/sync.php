<?php

return [
    'central_url' => env('SYNC_CENTRAL_URL'),
    'api_token' => env('SYNC_API_TOKEN'),
    'batch_size' => (int) env('SYNC_BATCH_SIZE', 500),
];
