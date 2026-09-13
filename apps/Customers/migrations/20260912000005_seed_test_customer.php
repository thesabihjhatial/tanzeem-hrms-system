<?php

use Phinx\Migration\AbstractMigration;

/**
 * Dev convenience login: admin@tanzeem.pk / pak@123. Fixture data
 * inserted directly via Phinx, not through CustomerManager/EmployeeManager.
 * Don't run this against a real production database.
 */
class SeedTestCustomer extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('customers')->insert([
            'company_name' => 'Test Company',
            'phone' => '03001234567',
            'province' => 'punjab',
            'city' => 'Lahore',
            'employee_count_range' => '1-10',
            'created_at' => $now,
        ])->saveData();

        $customerId = (int) $this->fetchRow("SELECT id FROM customers WHERE company_name = 'Test Company' ORDER BY id DESC LIMIT 1")['id'];

        $this->table('employees')->insert([
            'customer_id' => $customerId,
            'employee_id' => '1',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'admin@tanzeem.pk',
            'phone' => '03001234567',
            'password_hash' => password_hash('pak@123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
        ])->saveData();

        $planId = (int) $this->fetchRow('SELECT id FROM plans WHERE is_default = 1 LIMIT 1')['id'];

        $this->table('subscriptions')->insert([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'status' => 'trial',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at' => $now,
        ])->saveData();
    }

    public function down(): void
    {
        // FK cascades (customers -> employees, customers -> subscriptions) clean up the rest.
        $this->execute("DELETE FROM customers WHERE company_name = 'Test Company'");
    }
}
