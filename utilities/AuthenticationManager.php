<?php

// Tanzeem HRMS System Authentication Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Customers\Models\User;
use App\Core\Response;

class AuthenticationManager
{

    private const SESSION_CUSTOMER_ID = 'customer_id';

    private const SESSION_USER_ID = 'user_id';

    public static function attemptLogin(string $email, string $password): bool
    {

        $user = self::attempt($email, $password);

        if ($user === null) {

            return false;

        }

        self::login($user);

        return true;

    }

    public static function attempt(string $email, string $password): ?User
    {

        $user = User::findByEmail($email);

        if ($user === null || $user->password_hash === null || !password_verify($password, $user->password_hash)) {

            return null;

        }

        return $user;

    }

    public static function login(User $user): void
    {

        $_SESSION[self::SESSION_USER_ID] = $user->id;
        $_SESSION[self::SESSION_CUSTOMER_ID] = $user->customer_id;

    }

    public static function logout(): void
    {

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {

            session_destroy();

        }

    }

    public static function check(): bool
    {

        return isset($_SESSION[self::SESSION_CUSTOMER_ID]);

    }

    public static function guard(): ?Response
    {

        if (self::check()) {

            return null;

        }

        return new Response('', 302, ['Location' => '/login']);

    }

    public static function customerId(): ?int
    {

        return isset($_SESSION[self::SESSION_CUSTOMER_ID]) ? (int) $_SESSION[self::SESSION_CUSTOMER_ID] : null;

    }

    public static function userId(): ?int
    {

        return isset($_SESSION[self::SESSION_USER_ID]) ? (int) $_SESSION[self::SESSION_USER_ID] : null;

    }

}
