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

];
