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
                password_hash TEXT,
                role TEXT NOT NULL DEFAULT 'viewer',
                id_number TEXT,
                created_at TEXT,
                UNIQUE (email)
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

    public function test_find_by_email_is_global_not_scoped_to_a_customer(): void
    {
        Employee::create(1, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@tanzeem.test']);

        $employee = Employee::findByEmail('ada@tanzeem.test');

        $this->assertNotNull($employee);
        $this->assertSame(1, $employee->customer_id);
    }

    public function test_find_by_id_number_returns_null_when_not_set(): void
    {
        Employee::create(1, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@tanzeem.test']);

        $this->assertNull(Employee::findByIdNumber('3520112345671'));
    }

    public function test_find_by_id_number_finds_a_match(): void
    {
        Employee::create(1, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@tanzeem.test',
            'id_number' => '3520112345671',
        ]);

        $employee = Employee::findByIdNumber('3520112345671');

        $this->assertNotNull($employee);
        $this->assertSame('ada@tanzeem.test', $employee->email);
    }
}
