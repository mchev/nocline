<?php

/*
 * The package is developed inside the Nocline repository and is not required by the hub's composer.json.
 * This tiny PSR-4 autoloader lets the hub's test suite (`php artisan test`) exercise it with the hub's own
 * dependencies, since Orchestra Testbench is not installed.
 */
spl_autoload_register(function (string $class): void {
    $prefix = 'Nocline\\Laravel\\';

    if (str_starts_with($class, $prefix)) {
        $file = __DIR__.'/../src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';

        if (is_file($file)) {
            require_once $file;
        }
    }
});
