<?php

// Tanzeem HRMS System CSRF Manager developed and maintained by Sabih

namespace App\Utilities;

class CsrfManager
{

    private const SESSION_TOKEN_KEY = 'csrf_token';

    public static function token(): string
    {

        if (!isset($_SESSION[self::SESSION_TOKEN_KEY])) {

            $_SESSION[self::SESSION_TOKEN_KEY] = bin2hex(random_bytes(32));

        }

        return $_SESSION[self::SESSION_TOKEN_KEY];

    }

    public static function verify(?string $token): bool
    {

        return $token !== null && isset($_SESSION[self::SESSION_TOKEN_KEY]) && hash_equals($_SESSION[self::SESSION_TOKEN_KEY], $token);

    }

}
