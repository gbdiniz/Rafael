<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Default User Timezone
    |--------------------------------------------------------------------------
    |
    | Seeded on new users and used when the application needs a calendar-day
    | default before a user row exists. v1 does not update this from the browser.
    |
    */

    'default_timezone' => 'America/Sao_Paulo',

    /*
    |--------------------------------------------------------------------------
    | Bootstrap Admin Account
    |--------------------------------------------------------------------------
    |
    | Used only by DatabaseSeeder. Login resolves users through Auth::attempt().
    |
    */

    'admin' => [
        'name' => env('ADMIN_NAME'),
        'email' => env('ADMIN_EMAIL'),
        'password' => env('ADMIN_PASSWORD'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Voice Turn Upload
    |--------------------------------------------------------------------------
    */

    'voice_turn' => [
        'allowed_mimes' => [
            'audio/webm',
            'audio/ogg',
            'audio/mp4',
            'audio/mpeg',
            'video/webm',
        ],
        'max_kilobytes' => 10240,
    ],

    /*
    |--------------------------------------------------------------------------
    | Transcriber
    |--------------------------------------------------------------------------
    */

    'transcriber' => [
        'url' => env('TRANSCRIBER_URL', 'http://127.0.0.1:9000/asr'),
        'api_key' => env('TRANSCRIBER_API_KEY'),
        'model' => env('TRANSCRIBER_MODEL', 'base'),
        'connect_timeout' => 5,
        'timeout' => 60,
    ],

];
