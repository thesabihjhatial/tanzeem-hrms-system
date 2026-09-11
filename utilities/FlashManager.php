<?php

// Tanzeem HRMS System Flash Manager developed and maintained by Sabih

namespace App\Utilities;

/**
 * One-time session messages that survive a redirect — used for things
 * like the pwned-password warning, which must reach the user on the
 * page after signup, not the signup page itself. core/Controller.php's
 * view() consumes these automatically on every render, so callers only
 * ever need add().
 */
class FlashManager
{

    private const SESSION_KEY = 'flash_messages';

    public static function add(string $message, string $type = 'info'): void
    {

        $_SESSION[self::SESSION_KEY][] = ['message' => $message, 'type' => $type];

    }

    /** @return array<int, array{message: string, type: string}> */
    public static function consume(): array
    {

        $messages = $_SESSION[self::SESSION_KEY] ?? [];

        unset($_SESSION[self::SESSION_KEY]);

        return $messages;

    }

}
