<?php

namespace App\Core;

class Request
{
    public function __construct(
        public readonly string $method,
        public readonly string $uri,
        public readonly array $query = [],
        public readonly array $body = [],
        public readonly array $server = [],
    ) {
    }

    public static function fromGlobals(): self
    {
        $uri = self::stripScriptDirectory(
            parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/',
            $_SERVER['SCRIPT_NAME'] ?? '',
        );

        return new self(
            method: $_SERVER['REQUEST_METHOD'] ?? 'GET',
            uri: $uri,
            query: $_GET,
            body: $_POST,
            server: $_SERVER,
        );
    }

    /**
     * Makes routes work identically whether the app lives at the domain
     * root (shared hosting: public_html *is* the root) or in a subfolder
     * (local dev: htdocs/tanzeem-hrms-system/), by stripping the
     * directory the front controller itself lives in from the request path.
     */
    private static function stripScriptDirectory(string $path, string $scriptName): string
    {
        $scriptDir = rtrim(str_replace('\\', '/', dirname($scriptName)), '/');

        if ($scriptDir !== '' && str_starts_with($path, $scriptDir)) {
            $path = substr($path, strlen($scriptDir));
        }

        return $path === '' ? '/' : $path;
    }
}
