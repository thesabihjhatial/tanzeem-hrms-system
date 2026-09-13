<?php

namespace App\Apps\Employees\Models;

use App\Utilities\DatabaseManager;

class Employee
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly ?string $employee_id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $department,
        public readonly ?string $designation,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $city,
        public readonly ?string $password_hash,
        public readonly string $role,
        public readonly ?string $id_number,
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

    public static function findByIdNumber(string $idNumber): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE id_number = ?', [$idNumber]);

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

    /** @param array{employee_id?: ?string, first_name: string, last_name: string, department?: ?string, designation?: ?string, email: string, phone?: ?string, city?: ?string, password_hash?: ?string, role?: string, id_number?: ?string} $data */
    public static function create(int $customerId, array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO employees (customer_id, employee_id, first_name, last_name, department, designation, email, phone, city, password_hash, role, id_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $customerId,
                $data['employee_id'] ?? null,
                $data['first_name'],
                $data['last_name'],
                $data['department'] ?? null,
                $data['designation'] ?? null,
                $data['email'],
                $data['phone'] ?? null,
                $data['city'] ?? null,
                $data['password_hash'] ?? null,
                $data['role'] ?? 'viewer',
                $data['id_number'] ?? null,
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
            (int) $row['customer_id'],
            $row['employee_id'],
            $row['first_name'],
            $row['last_name'],
            $row['department'],
            $row['designation'],
            $row['email'],
            $row['phone'],
            $row['city'],
            $row['password_hash'],
            $row['role'],
            $row['id_number'],
        );
    }
}
