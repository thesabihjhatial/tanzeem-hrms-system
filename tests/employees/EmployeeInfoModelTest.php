<?php

namespace App\Tests\Employees;

use App\Apps\Employees\Models\EmployeeInfo;
use App\Utilities\DatabaseManager;
use PHPUnit\Framework\TestCase;

class EmployeeInfoModelTest extends TestCase
{
    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $pdo->exec(<<<SQL
            CREATE TABLE employee_info (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL UNIQUE,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                department TEXT,
                designation TEXT,
                phone TEXT,
                city TEXT,
                id_number TEXT UNIQUE,
                created_at TEXT
            )
            SQL);
    }

    public function test_it_creates_and_reads_an_employee_info_row(): void
    {
        EmployeeInfo::create(['employee_id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $info = EmployeeInfo::findByEmployeeId(1);

        $this->assertNotNull($info);
        $this->assertSame('Ada', $info->first_name);
        $this->assertSame('Lovelace', $info->last_name);
    }

    public function test_find_by_employee_id_returns_null_when_missing(): void
    {
        $this->assertNull(EmployeeInfo::findByEmployeeId(999));
    }

    public function test_find_by_id_number_returns_null_when_not_set(): void
    {
        EmployeeInfo::create(['employee_id' => 1, 'first_name' => 'Ada', 'last_name' => 'Lovelace']);

        $this->assertNull(EmployeeInfo::findByIdNumber('3520112345671'));
    }

    public function test_find_by_id_number_finds_a_match(): void
    {
        EmployeeInfo::create([
            'employee_id' => 1,
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'id_number' => '3520112345671',
        ]);

        $info = EmployeeInfo::findByIdNumber('3520112345671');

        $this->assertNotNull($info);
        $this->assertSame(1, $info->employee_id);
    }
}
