<?php

/**
 * Router for `php -S` in e2e / screenshot runs.
 * Same as Laravel's server.php but without access-log writes to stdout
 * (avoids "Broken pipe" warnings leaking into HTTP responses when the client aborts).
 */
$publicPath = getcwd();

$uri = urldecode(
    parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? ''
);

if ($uri !== '/' && file_exists($publicPath.$uri)) {
    return false;
}

require_once $publicPath.'/index.php';
