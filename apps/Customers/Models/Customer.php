<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;

class Customer
{
    public function __construct(
        public readonly int $id,
        public readonly string $company_name,
        public readonly string $customer_type,
        public readonly string $phone,
        public readonly string $province,
        public readonly string $city,
        public readonly string $employee_count_range,
        public readonly ?string $ntn,
        public readonly ?string $eobi_registration_no,
        public readonly ?string $pessi_registration_no,
        public readonly ?string $business_type,
    ) {
    }

    public static function find(int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM customers WHERE id = ?', [$id]);

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{company_name: string, customer_type: string, phone: string, province: string, city: string, employee_count_range: string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO customers (company_name, customer_type, phone, province, city, employee_count_range, created_at) VALUES (?, ?, ?, ?, ?, ?, ?)',
            [
                $data['company_name'],
                $data['customer_type'],
                $data['phone'],
                $data['province'],
                $data['city'],
                $data['employee_count_range'],
                date('Y-m-d H:i:s'),
            ],
        );

        return self::find((int) DatabaseManager::lastInsertId());
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['company_name'],
            $row['customer_type'],
            $row['phone'],
            $row['province'],
            $row['city'],
            $row['employee_count_range'],
            $row['ntn'],
            $row['eobi_registration_no'],
            $row['pessi_registration_no'],
            $row['business_type'],
        );
    }
}
