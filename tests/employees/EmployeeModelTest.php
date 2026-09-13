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
                uuid TEXT NOT NULL,
                customer_id INTEGER NOT NULL,
                employee_id TEXT,
                email TEXT NOT NULL,
                password_hash TEXT,
                role TEXT NOT NULL DEFAULT 'viewer',
                created_at TEXT,
                UNIQUE (email)
            )
            SQL);
    }

    public function test_it_creates_and_reads_an_employee(): void
    {
        Employee::create(1, ['email' => 'ada@tanzeem.test']);

        $employees = Employee::all(1);

        $this->assertCount(1, $employees);
        $this->assertSame('ada@tanzeem.test', $employees[0]->email);
    }

    public function test_all_only_returns_employees_for_the_given_customer(): void
    {
        Employee::create(1, ['email' => 'ada@tanzeem.test']);
        Employee::create(2, ['email' => 'alan@tanzeem.test']);

        $this->assertCount(1, Employee::all(1));
        $this->assertCount(1, Employee::all(2));
    }

    public function test_find_returns_null_for_missing_employee(): void
    {
        $this->assertNull(Employee::find(1, 999));
    }

    public function test_find_by_uuid_scopes_to_the_given_customer(): void
    {
        $employee = Employee::create(1, ['email' => 'ada@tanzeem.test']);

        $this->assertNull(Employee::findByUuid(2, $employee->uuid));
        $this->assertSame($employee->id, Employee::findByUuid(1, $employee->uuid)->id);
    }

    public function test_find_by_email_is_global_not_scoped_to_a_customer(): void
    {
        Employee::create(1, ['email' => 'ada@tanzeem.test']);

        $employee = Employee::findByEmail('ada@tanzeem.test');

        $this->assertNotNull($employee);
        $this->assertSame(1, $employee->customer_id);
    }
}
