<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Would you like the install button to appear on all pages?
      Set true/false
    |--------------------------------------------------------------------------
    */

    'install-button' => false,

    /*
    |--------------------------------------------------------------------------
    | PWA Manifest Configuration
    |--------------------------------------------------------------------------
    |  php artisan erag:update-manifest
    */

    'manifest' => [
        'name' => 'Horin',
        'short_name' => 'Horin',
        'background_color' => '#ffffff',
        'display' => 'standalone',
        'description' => 'Horin - Your trading companion',
        'theme_color' => '#000000',
        'icons' => [
            [
                'src' => 'images/icons/icon-48x48.png',
                'sizes' => '48x48',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-72x72.png',
                'sizes' => '72x72',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-96x96.png',
                'sizes' => '96x96',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-128x128.png',
                'sizes' => '128x128',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-144x144.png',
                'sizes' => '144x144',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-152x152.png',
                'sizes' => '152x152',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-192x192.png',
                'sizes' => '192x192',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-256x256.png',
                'sizes' => '256x256',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-384x384.png',
                'sizes' => '384x384',
                'type' => 'image/png',
            ],
            [
                'src' => 'images/icons/icon-512x512.png',
                'sizes' => '512x512',
                'type' => 'image/png',
            ],
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Debug Configuration
    |--------------------------------------------------------------------------
    | Toggles the application's debug mode based on the environment variable
    */

    'debug' => env('APP_DEBUG', false),

    /*
    |--------------------------------------------------------------------------
    | Livewire Integration
    |--------------------------------------------------------------------------
    | Set to true if you're using Livewire in your application to enable
    | Livewire-specific PWA optimizations or features.
    */

    'livewire-app' => false,
];
