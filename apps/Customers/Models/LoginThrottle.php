<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;
use PDOException;

class LoginThrottle
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly int $attempts,
        public readonly ?string $locked_until,
    ) {
    }

    public static function findByEmail(string $email): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM login_throttles WHERE email = ?', [$email]);

        return $row ? self::fromRow($row) : null;
    }

    public function isLocked(): bool
    {
        return $this->locked_until !== null && strtotime($this->locked_until) > time();
    }

    public static function recordFailure(string $email, int $maxAttempts, int $lockoutSeconds): void
    {
        $now = date('Y-m-d H:i:s');
        $existing = self::findByEmail($email);
        $attempts = ($existing?->attempts ?? 0) + 1;
        $lockedUntil = $attempts >= $maxAttempts ? date('Y-m-d H:i:s', time() + $lockoutSeconds) : null;

        try {

            DatabaseManager::execute(
                'INSERT INTO login_throttles (email, attempts, locked_until, updated_at) VALUES (?, ?, ?, ?)',
                [$email, $attempts, $lockedUntil, $now],
            );

        } catch (PDOException) {

            DatabaseManager::execute(
                'UPDATE login_throttles SET attempts = ?, locked_until = ?, updated_at = ? WHERE email = ?',
                [$attempts, $lockedUntil, $now, $email],
            );

        }
    }

    public static function clear(string $email): bool
    {
        return DatabaseManager::execute('DELETE FROM login_throttles WHERE email = ?', [$email]);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['email'],
            (int) $row['attempts'],
            $row['locked_until'],
        );
    }
}
