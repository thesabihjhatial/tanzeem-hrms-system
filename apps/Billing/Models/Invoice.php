<?php

namespace App\Apps\Billing\Models;

use App\Utilities\DatabaseManager;

class Invoice
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly int $subscription_id,
        public readonly float $amount,
        public readonly string $status,
        public readonly ?string $paid_at,
    ) {
    }

    public static function find(int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM invoices WHERE id = ?', [$id]);

        return $row ? self::fromRow($row) : null;
    }

    /** @return array<int, self> */
    public static function forCustomer(int $customerId): array
    {
        $rows = DatabaseManager::select(
            'SELECT * FROM invoices WHERE customer_id = ? ORDER BY id DESC',
            [$customerId],
        );

        return array_map(self::fromRow(...), $rows);
    }

    /** @param array{customer_id: int, subscription_id: int, amount: float, status: string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO invoices (customer_id, subscription_id, amount, status, issued_at) VALUES (?, ?, ?, ?, ?)',
            [$data['customer_id'], $data['subscription_id'], $data['amount'], $data['status'], date('Y-m-d H:i:s')],
        );

        return self::find((int) DatabaseManager::lastInsertId());
    }

    public static function markPaid(int $id): bool
    {
        return DatabaseManager::execute(
            'UPDATE invoices SET status = ?, paid_at = ? WHERE id = ?',
            ['paid', date('Y-m-d H:i:s'), $id],
        );
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['customer_id'],
            (int) $row['subscription_id'],
            (float) $row['amount'],
            $row['status'],
            $row['paid_at'],
        );
    }
}
