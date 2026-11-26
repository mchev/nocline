<?php

/**
 * Pest / PHPUnit bootstrap. Env from phpunit.xml must win over a local .env
 * (Herd, Redis, file cache) so throttles do not leak across tests or workers.
 */
require __DIR__.'/../vendor/autoload.php';

$testingEnv = [
    'APP_ENV' => 'testing',
    'CACHE_STORE' => 'array',
    'APP_URL' => 'http://localhost',
];

foreach ($testingEnv as $key => $value) {
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
    putenv("{$key}={$value}");
}
