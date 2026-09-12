<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;

class User
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly string $name,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $id_number,
        public readonly ?string $password_hash,
        public readonly string $role,
    ) {
    }

    public static function find(int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM users WHERE id = ?', [$id]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM users WHERE email = ?', [$email]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByIdNumber(string $idNumber): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM users WHERE id_number = ?', [$idNumber]);

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{customer_id: int, name: string, email: string, phone: ?string, id_number: ?string, password_hash: ?string, role: string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO users (customer_id, name, email, phone, id_number, password_hash, role, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $data['customer_id'],
                $data['name'],
                $data['email'],
                $data['phone'] ?? null,
                $data['id_number'] ?? null,
                $data['password_hash'] ?? null,
                $data['role'],
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
            (int) $row['customer_id'],
            $row['name'],
            $row['email'],
            $row['phone'],
            $row['id_number'],
            $row['password_hash'],
            $row['role'],
        );
    }
}
