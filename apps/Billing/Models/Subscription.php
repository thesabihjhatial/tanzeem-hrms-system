<?php

namespace App\Apps\Billing\Models;

use App\Utilities\DatabaseManager;

class Subscription
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly int $plan_id,
        public readonly string $status,
        public readonly ?string $trial_ends_at,
        public readonly ?string $current_period_end,
    ) {
    }

    public static function find(int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM subscriptions WHERE id = ?', [$id]);

        return $row ? self::fromRow($row) : null;
    }

    public static function forCustomer(int $customerId): ?self
    {
        $row = DatabaseManager::selectOne(
            'SELECT * FROM subscriptions WHERE customer_id = ? ORDER BY id DESC LIMIT 1',
            [$customerId],
        );

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{customer_id: int, plan_id: int, status: string, trial_ends_at: ?string, current_period_end: ?string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO subscriptions (customer_id, plan_id, status, trial_ends_at, current_period_end, created_at) VALUES (?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'],
                $data['plan_id'],
                $data['status'],
                $data['trial_ends_at'],
                $data['current_period_end'],
                date('Y-m-d H:i:s'),
            ],
        );

        return self::find((int) DatabaseManager::lastInsertId());
    }

    public static function updateStatus(int $id, string $status): bool
    {
        return DatabaseManager::execute('UPDATE subscriptions SET status = ? WHERE id = ?', [$status, $id]);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['customer_id'],
            (int) $row['plan_id'],
            $row['status'],
            $row['trial_ends_at'],
            $row['current_period_end'],
        );
    }
}
