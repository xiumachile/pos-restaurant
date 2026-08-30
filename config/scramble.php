<?php

use Illuminate\Routing\Route;

return [
    'api_path' => 'api',
    'api_domain' => '',
    'servers' => [
        'Local Development' => 'http://localhost:8000',
        'Production' => env('APP_URL', 'https://api.example.com'),
    ],
    'info' => [
        'version' => '1.0.0', 
        'description' => 'POS Restaurant API'
    ],
    'auth' => [
        'security_schemes' => [
            'bearerAuth' => [
                'type' => 'http', 
                'scheme' => 'bearer', 
                'bearerFormat' => 'JWT'
            ]
        ],
        'security' => [['bearerAuth' => []]],
    ],
    'routes' => function (Route $route) { 
        return str_contains($route->uri, 'api/v1'); 
    },
    'ui' => ['path' => 'docs/api'],
    'export_path' => storage_path('app/public/docs/api.json'),
    'extensions' => [
        \App\Docs\Extensions\ModuleTagExtension::class,
        \App\Docs\Extensions\DescriptionExtension::class,
    ],
];
