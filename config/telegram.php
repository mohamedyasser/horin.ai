<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    |
    | Your Telegram Bot API token obtained from @BotFather.
    |
    */

    'bot_token' => env('TELEGRAM_BOT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Username
    |--------------------------------------------------------------------------
    |
    | Your bot's username (without the @ symbol). Used for the Login Widget.
    |
    */

    'bot_username' => env('TELEGRAM_BOT_USERNAME'),

    /*
    |--------------------------------------------------------------------------
    | Webhook Secret
    |--------------------------------------------------------------------------
    |
    | A secret token to validate incoming webhook requests from Telegram.
    |
    */

    'webhook_secret' => env('TELEGRAM_WEBHOOK_SECRET'),

    /*
    |--------------------------------------------------------------------------
    | Authentication Timeout
    |--------------------------------------------------------------------------
    |
    | Maximum age (in seconds) of the auth_date parameter from the Telegram
    | Login Widget. Default is 24 hours (86400 seconds).
    |
    */

    'auth_timeout' => 86400,

];
