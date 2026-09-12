<?php

// Tanzeem HRMS System Authentication Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Customers\Models\LoginThrottle;
use App\Apps\Customers\Models\User;
use App\Core\Response;

class AuthenticationManager
{

    private const LOGIN_LOCKOUT_SECONDS = 180;

    private const MAX_LOGIN_ATTEMPTS = 5;

    private const SESSION_AUTHENTICATED_AT = 'authenticated_at';

    private const SESSION_CUSTOMER_ID = 'customer_id';

    public const SESSION_TIMEOUT_SECONDS = 10800;

    private const SESSION_USER_ID = 'user_id';

    public static function configureSession(): void
    {

        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => EnvironmentManager::isProduction(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

    }

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

        $passwordCorrect = password_verify($password, $user->password_hash ?? self::dummyHash());

        $throttle = LoginThrottle::findByEmail($email);

        if ($throttle !== null && $throttle->isLocked()) {

            return null;

        }

        if ($user === null || $user->password_hash === null || !$passwordCorrect) {

            LoginThrottle::recordFailure($email, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCKOUT_SECONDS);

            return null;

        }

        LoginThrottle::clear($email);

        return $user;

    }

    public static function login(User $user): void
    {

        if (session_status() === PHP_SESSION_ACTIVE) {

            session_regenerate_id(true);

        }

        $_SESSION[self::SESSION_USER_ID] = $user->id;
        $_SESSION[self::SESSION_CUSTOMER_ID] = $user->customer_id;
        $_SESSION[self::SESSION_AUTHENTICATED_AT] = time();

    }

    public static function logout(): void
    {

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {

            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();

        }

    }

    public static function check(): bool
    {

        if (!isset($_SESSION[self::SESSION_CUSTOMER_ID], $_SESSION[self::SESSION_USER_ID])) {

            return false;

        }

        $authenticatedAt = $_SESSION[self::SESSION_AUTHENTICATED_AT] ?? 0;

        if (time() - $authenticatedAt > self::SESSION_TIMEOUT_SECONDS) {

            self::logout();

            return false;

        }

        if (User::find((int) $_SESSION[self::SESSION_USER_ID]) === null) {

            self::logout();

            return false;

        }

        return true;

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

        return self::check() ? (int) $_SESSION[self::SESSION_CUSTOMER_ID] : null;

    }

    public static function userId(): ?int
    {

        return self::check() ? (int) $_SESSION[self::SESSION_USER_ID] : null;

    }

    public static function currentUser(): ?User
    {

        $userId = self::userId();

        return $userId !== null ? User::find($userId) : null;

    }

    private static function dummyHash(): string
    {

        static $hash = null;

        return $hash ??= password_hash('tanzeem-timing-safety-placeholder', PASSWORD_ARGON2ID);

    }

}
