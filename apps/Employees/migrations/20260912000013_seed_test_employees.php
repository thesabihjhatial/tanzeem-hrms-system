<?php

use Phinx\Migration\AbstractMigration;

/**
 * Dev convenience data for the dashboard: 3 employees under Test Company
 * (customer id 1). Inserted directly via Phinx, not through EmployeeManager.
 * Don't run this against a real production database.
 */
class SeedTestEmployees extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');
        $customerId = (int) $this->fetchRow("SELECT id FROM customers WHERE company_name = 'Test Company' ORDER BY id ASC LIMIT 1")['id'];

        $employees = [
            ['employee_id' => 'EMP-0001', 'first_name' => 'Ayesha', 'last_name' => 'Khan', 'department' => 'Engineering', 'designation' => 'Software Engineer', 'email' => 'ayesha.khan@testcompany.test', 'phone' => '03001112233', 'city' => 'Lahore'],
            ['employee_id' => 'EMP-0002', 'first_name' => 'Bilal', 'last_name' => 'Ahmed', 'department' => 'Sales', 'designation' => 'Account Manager', 'email' => 'bilal.ahmed@testcompany.test', 'phone' => '03002223344', 'city' => 'Karachi'],
            ['employee_id' => 'EMP-0003', 'first_name' => 'Sana', 'last_name' => 'Malik', 'department' => 'Human Resources', 'designation' => 'HR Executive', 'email' => 'sana.malik@testcompany.test', 'phone' => '03003334455', 'city' => 'Islamabad'],
        ];

        foreach ($employees as $employee) {
            $this->table('employees')->insert([
                'customer_id' => $customerId,
                'employee_id' => $employee['employee_id'],
                'first_name' => $employee['first_name'],
                'last_name' => $employee['last_name'],
                'department' => $employee['department'],
                'designation' => $employee['designation'],
                'email' => $employee['email'],
                'phone' => $employee['phone'],
                'city' => $employee['city'],
                'created_at' => $now,
            ])->saveData();
        }
    }

    public function down(): void
    {
        $this->execute("DELETE FROM employees WHERE employee_id IN ('EMP-0001', 'EMP-0002', 'EMP-0003')");
    }
}
