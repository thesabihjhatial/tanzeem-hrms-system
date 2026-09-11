<?php

namespace App\Apps\Employees\Models;

use App\Utilities\DatabaseManager;

class Employee
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly string $email,
    ) {
    }

    /** @return array<int, self> */
    public static function all(int $customerId): array
    {
        $rows = DatabaseManager::select('SELECT * FROM employees WHERE customer_id = ? ORDER BY id', [$customerId]);

        return array_map(self::fromRow(...), $rows);
    }

    public static function find(int $customerId, int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE customer_id = ? AND id = ?', [$customerId, $id]);

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{first_name: string, last_name: string, email: string} $data */
    public static function create(int $customerId, array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO employees (customer_id, first_name, last_name, email, created_at) VALUES (?, ?, ?, ?, ?)',
            [$customerId, $data['first_name'], $data['last_name'], $data['email'], date('Y-m-d H:i:s')],
        );

        return self::find($customerId, (int) DatabaseManager::lastInsertId());
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['customer_id'], $row['first_name'], $row['last_name'], $row['email']);
    }
}
