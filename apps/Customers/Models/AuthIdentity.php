<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;

/**
 * Not used by any flow yet — a landing place for OAuth provider links
 * (Google, Microsoft, etc.) once that work starts, so it doesn't need
 * its own schema migration then.
 */
class AuthIdentity
{
    public function __construct(
        public readonly int $id,
        public readonly int $employee_id,
        public readonly string $provider,
        public readonly string $provider_user_id,
    ) {
    }

    public static function findByProvider(string $provider, string $providerUserId): ?self
    {
        $row = DatabaseManager::selectOne(
            'SELECT * FROM auth_identities WHERE provider = ? AND provider_user_id = ?',
            [$provider, $providerUserId],
        );

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{employee_id: int, provider: string, provider_user_id: string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO auth_identities (employee_id, provider, provider_user_id, created_at) VALUES (?, ?, ?, ?)',
            [$data['employee_id'], $data['provider'], $data['provider_user_id'], date('Y-m-d H:i:s')],
        );

        return self::findByProvider($data['provider'], $data['provider_user_id']);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['employee_id'], $row['provider'], $row['provider_user_id']);
    }
}
