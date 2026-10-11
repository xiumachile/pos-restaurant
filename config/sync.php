<?php

return [
    'enabled' => env('SYNC_ENABLED', false),
    'cloud_url' => env('SYNC_CLOUD_URL', 'https://api.pos-restaurant.com/v1/sync/receive'),
    'api_token' => env('SYNC_API_TOKEN', ''),
];
