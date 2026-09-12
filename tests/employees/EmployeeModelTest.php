<?php

namespace App\Tests\Employees;

use App\Apps\Employees\Models\Employee;
use App\Utilities\DatabaseManager;
use PHPUnit\Framework\TestCase;

class EmployeeModelTest extends TestCase
{
    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $pdo->exec(<<<SQL
            CREATE TABLE employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                employee_id TEXT,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                department TEXT,
                designation TEXT,
                email TEXT NOT NULL,
                phone TEXT,
                city TEXT,
                created_at TEXT,
                UNIQUE (customer_id, email)
            )
            SQL);
    }

    public function test_it_creates_and_reads_an_employee(): void
    {
        Employee::create(1, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@tanzeem.test',
        ]);

        $employees = Employee::all(1);

        $this->assertCount(1, $employees);
        $this->assertSame('Ada', $employees[0]->first_name);
    }

    public function test_all_only_returns_employees_for_the_given_customer(): void
    {
        Employee::create(1, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@tanzeem.test']);
        Employee::create(2, ['first_name' => 'Alan', 'last_name' => 'Turing', 'email' => 'alan@tanzeem.test']);

        $this->assertCount(1, Employee::all(1));
        $this->assertCount(1, Employee::all(2));
    }

    public function test_find_returns_null_for_missing_employee(): void
    {
        $this->assertNull(Employee::find(1, 999));
    }
}
