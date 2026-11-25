<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Nocline hub URL
    |--------------------------------------------------------------------------
    |
    | Base URL of your Nocline instance, without a trailing path
    | (e.g. https://nocline.example.com).
    |
    */

    'url' => env('NOCLINE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default heartbeat token
    |--------------------------------------------------------------------------
    |
    | Used when you ping without naming a heartbeat. Copy the token from the
    | "scheduled task" page in Nocline (the last part of the ping address).
    |
    */

    'heartbeat_token' => env('NOCLINE_HEARTBEAT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Named heartbeats
    |--------------------------------------------------------------------------
    |
    | name => token. Lets you write ->pingNoclineOnSuccess('backup') and keep the
    | secrets in your environment file.
    |
    */

    'heartbeats' => [
        // 'backup' => env('NOCLINE_HEARTBEAT_BACKUP'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Timeout (seconds)
    |--------------------------------------------------------------------------
    |
    | A ping never blocks your application for longer than this, and errors are
    | swallowed (logged at debug level without the secret token).
    |
    */

    'timeout' => 3,

];
