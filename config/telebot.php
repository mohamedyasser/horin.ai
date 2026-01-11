<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Default Bot
    |--------------------------------------------------------------------------
    |
    | The default bot that will be used when no bot is specified.
    |
    */
    'default' => 'horin',

    /*
    |--------------------------------------------------------------------------
    | Bots
    |--------------------------------------------------------------------------
    |
    | Configure your Telegram bots here.
    |
    */
    'bots' => [
        'horin' => [
            'token' => env('TELEGRAM_BOT_TOKEN'),
            'name' => env('TELEGRAM_BOT_USERNAME'),

            // Handler kernel for processing updates
            'kernel' => \App\Telegram\HorinKernel::class,

            // Webhook configuration
            'webhook' => [
                'url' => env('APP_URL').'/telegram/webhook',
                'certificate' => null,
                'ip_address' => null,
                'max_connections' => 40,
                'allowed_updates' => ['message', 'callback_query'],
                'drop_pending_updates' => false,
                'secret_token' => env('TELEGRAM_WEBHOOK_SECRET'),
            ],
        ],
    ],
];
