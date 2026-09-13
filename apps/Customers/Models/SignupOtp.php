<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;
use PDOException;

class SignupOtp
{
    public function __construct(
        public readonly int $id,
        public readonly string $email,
        public readonly string $otp_hash,
        public readonly int $attempts,
        public readonly string $payload,
        public readonly string $expires_at,
    ) {
    }

    public static function findByEmail(string $email): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM otps WHERE email = ?', [$email]);

        return $row ? self::fromRow($row) : null;
    }

    /**
     * Insert-or-replace, so two concurrent signup attempts for the same
     * email can never race each other into an uncaught unique-constraint
     * violation — whichever finishes last simply wins. Also sweeps out
     * any unrelated expired rows on every call, since a real signup
     * attempt is a natural, frequent trigger point for that cleanup
     * without needing a dedicated scheduled job.
     *
     * @param array{email: string, otp_hash: string, payload: string, expires_at: string} $data
     */
    public static function create(array $data): self
    {
        self::deleteExpired();

        $now = date('Y-m-d H:i:s');

        try {

            DatabaseManager::execute(
                'INSERT INTO otps (email, otp_hash, attempts, payload, expires_at, created_at) VALUES (?, ?, 0, ?, ?, ?)',
                [$data['email'], $data['otp_hash'], $data['payload'], $data['expires_at'], $now],
            );

        } catch (PDOException) {

            // A concurrent request for the same email won the insert race —
            // fold this attempt into an update instead of failing outright.
            DatabaseManager::execute(
                'UPDATE otps SET otp_hash = ?, attempts = 0, payload = ?, expires_at = ?, created_at = ? WHERE email = ?',
                [$data['otp_hash'], $data['payload'], $data['expires_at'], $now, $data['email']],
            );

        }

        return self::findByEmail($data['email']);
    }

    public static function incrementAttempts(string $email): bool
    {
        return DatabaseManager::execute('UPDATE otps SET attempts = attempts + 1 WHERE email = ?', [$email]);
    }

    public static function deleteByEmail(string $email): bool
    {
        return DatabaseManager::execute('DELETE FROM otps WHERE email = ?', [$email]);
    }

    public static function deleteExpired(): bool
    {
        return DatabaseManager::execute('DELETE FROM otps WHERE expires_at < ?', [date('Y-m-d H:i:s')]);
    }

    public function isExpired(): bool
    {
        return strtotime($this->expires_at) < time();
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['email'],
            $row['otp_hash'],
            (int) $row['attempts'],
            $row['payload'],
            $row['expires_at'],
        );
    }
}
