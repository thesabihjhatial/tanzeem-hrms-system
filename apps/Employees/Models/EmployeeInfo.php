<?php

namespace App\Apps\Employees\Models;

use App\Utilities\DatabaseManager;

class EmployeeInfo
{
    public function __construct(
        public readonly int $id,
        public readonly int $employee_id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $department,
        public readonly ?string $designation,
        public readonly ?string $phone,
        public readonly ?string $city,
        public readonly ?string $id_number,
        public readonly ?string $photo,
    ) {
    }

    public static function findByEmployeeId(int $employeeId): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employee_info WHERE employee_id = ?', [$employeeId]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByIdNumber(string $idNumber): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employee_info WHERE id_number = ?', [$idNumber]);

        return $row ? self::fromRow($row) : null;
    }

    /** @return array<int, self> keyed by employee_id */
    public static function allForCustomer(int $customerId): array
    {
        $rows = DatabaseManager::select(
            'SELECT ei.* FROM employee_info ei JOIN employees e ON e.id = ei.employee_id WHERE e.customer_id = ?',
            [$customerId],
        );

        $result = [];

        foreach ($rows as $row) {
            $info = self::fromRow($row);
            $result[$info->employee_id] = $info;
        }

        return $result;
    }

    /** @param array{employee_id: int, first_name: string, last_name: string, department?: ?string, designation?: ?string, phone?: ?string, city?: ?string, id_number?: ?string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO employee_info (employee_id, first_name, last_name, department, designation, phone, city, id_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['employee_id'],
                $data['first_name'],
                $data['last_name'],
                $data['department'] ?? null,
                $data['designation'] ?? null,
                $data['phone'] ?? null,
                $data['city'] ?? null,
                $data['id_number'] ?? null,
                date('Y-m-d H:i:s'),
            ],
        );

        return self::findByEmployeeId($data['employee_id']);
    }

    public static function updatePhoto(int $employeeId, ?string $photo): bool
    {
        return DatabaseManager::execute('UPDATE employee_info SET photo = ? WHERE employee_id = ?', [$photo, $employeeId]);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['employee_id'],
            $row['first_name'],
            $row['last_name'],
            $row['department'],
            $row['designation'],
            $row['phone'],
            $row['city'],
            $row['id_number'],
            $row['photo'],
        );
    }
}
