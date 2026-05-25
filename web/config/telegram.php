<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Username
    |--------------------------------------------------------------------------
    | The username of the Telegram bot (without @).
    | Used for generating deep-link URLs: https://t.me/{username}?start=...
    */
    'bot_username' => env('TELEGRAM_BOT_USERNAME', ''),

    /*
    |--------------------------------------------------------------------------
    | Telegram Bot Token
    |--------------------------------------------------------------------------
    | Used if the website needs to call Telegram Bot API directly.
    */
    'bot_token' => env('TELEGRAM_BOT_TOKEN', ''),

    /*
    |--------------------------------------------------------------------------
    | Login Token TTL (minutes)
    |--------------------------------------------------------------------------
    | How long a login token is valid before it expires.
    */
    'login_token_ttl_minutes' => (int) env('WEB_LOGIN_TOKEN_TTL_MINUTES', 5),

    /*
    |--------------------------------------------------------------------------
    | Remember Me Duration (days)
    |--------------------------------------------------------------------------
    | How long the "remember me" cookie lasts.
    */
    'remember_me_days' => (int) env('WEB_REMEMBER_ME_DAYS', 30),

    /*
    |--------------------------------------------------------------------------
    | Website Domain
    |--------------------------------------------------------------------------
    | The domain of this website.
    */
    'domain' => env('WEBSITE_DOMAIN', ''),
];
