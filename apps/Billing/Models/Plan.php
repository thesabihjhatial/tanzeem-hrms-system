<?php

namespace App\Apps\Billing\Models;

use App\Utilities\DatabaseManager;

class Plan
{
    public function __construct(
        public readonly int $id,
        public readonly string $name,
        public readonly float $price,
        public readonly int $employee_limit,
        public readonly string $billing_cycle,
        public readonly bool $is_default,
    ) {
    }

    public static function find(int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM plans WHERE id = ?', [$id]);

        return $row ? self::fromRow($row) : null;
    }

    public static function default(): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM plans WHERE is_default = 1 LIMIT 1');

        return $row ? self::fromRow($row) : null;
    }

    /** @return array<int, self> */
    public static function all(): array
    {
        $rows = DatabaseManager::select('SELECT * FROM plans ORDER BY price ASC');

        return array_map(self::fromRow(...), $rows);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            $row['name'],
            (float) $row['price'],
            (int) $row['employee_limit'],
            $row['billing_cycle'],
            (bool) $row['is_default'],
        );
    }
}
