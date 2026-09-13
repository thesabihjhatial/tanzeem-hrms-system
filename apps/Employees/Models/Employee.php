<?php

namespace App\Apps\Employees\Models;

use App\Utilities\DatabaseManager;
use App\Utilities\UuidManager;

class Employee
{
    public function __construct(
        public readonly int $id,
        public readonly string $uuid,
        public readonly int $customer_id,
        public readonly ?string $employee_id,
        public readonly string $email,
        public readonly ?string $password_hash,
        public readonly string $role,
        public readonly string $created_at,
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

    public static function findByEmail(string $email): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE email = ?', [$email]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByUuid(int $customerId, string $uuid): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE customer_id = ? AND uuid = ?', [$customerId, $uuid]);

        return $row ? self::fromRow($row) : null;
    }

    public static function maxNumericEmployeeId(int $customerId): int
    {
        $rows = DatabaseManager::select('SELECT employee_id FROM employees WHERE customer_id = ? AND employee_id IS NOT NULL', [$customerId]);

        $max = 0;

        foreach ($rows as $row) {
            if (preg_match('/(\d+)$/', (string) $row['employee_id'], $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    /** @param array{employee_id?: ?string, email: string, password_hash?: ?string, role?: string} $data */
    public static function create(int $customerId, array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO employees (uuid, customer_id, employee_id, email, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                UuidManager::v4(),
                $customerId,
                $data['employee_id'] ?? null,
                $data['email'],
                $data['password_hash'] ?? null,
                $data['role'] ?? 'viewer',
                date('Y-m-d H:i:s'),
            ],
        );

        return self::find($customerId, (int) DatabaseManager::lastInsertId());
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['uuid'],
            (int) $row['customer_id'],
            $row['employee_id'],
            $row['email'],
            $row['password_hash'],
            $row['role'],
            $row['created_at'],
        );
    }
}
