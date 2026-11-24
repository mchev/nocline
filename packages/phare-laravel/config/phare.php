<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Phare hub URL
    |--------------------------------------------------------------------------
    |
    | Base URL of your Phare instance, without a trailing path
    | (e.g. https://phare.example.com).
    |
    */

    'url' => env('PHARE_URL'),

    /*
    |--------------------------------------------------------------------------
    | Default heartbeat token
    |--------------------------------------------------------------------------
    |
    | Used when you ping without naming a heartbeat. Copy the token from the
    | "scheduled task" page in Phare (the last part of the ping address).
    |
    */

    'heartbeat_token' => env('PHARE_HEARTBEAT_TOKEN'),

    /*
    |--------------------------------------------------------------------------
    | Named heartbeats
    |--------------------------------------------------------------------------
    |
    | name => token. Lets you write ->pingPhareOnSuccess('backup') and keep the
    | secrets in your environment file.
    |
    */

    'heartbeats' => [
        // 'backup' => env('PHARE_HEARTBEAT_BACKUP'),
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
