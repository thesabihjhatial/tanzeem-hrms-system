<?php

/**
 * Router for `php -S` (php manage.php serve). PHP's built-in server
 * ignores .htaccess entirely, so this mirrors its two rules by hand:
 * block direct access to app internals, and fall through to index.php
 * for anything that isn't a real file. Not used in production —
 * Apache reads .htaccess directly there.
 */

$uri = urldecode(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

if (preg_match('#^/(core|apps|config|storage|tests|docs|vendor|utilities|sql)(/|$)#', $uri)
    || preg_match('#^(composer\.(json|lock)|phinx\.php|manage\.php|phpunit\.xml|DEPLOY\.md|\.env.*)$#', ltrim($uri, '/'))
) {
    http_response_code(403);
    exit('Forbidden');
}

$file = __DIR__ . '/../' . ltrim($uri, '/');
if ($uri !== '/' && is_file($file)) {
    return false;
}

require __DIR__ . '/../index.php';
