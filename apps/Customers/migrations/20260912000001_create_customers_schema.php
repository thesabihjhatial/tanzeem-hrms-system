<?php

use Phinx\Migration\AbstractMigration;

/**
 * Consolidated baseline for the Customers app's schema. Creates the
 * `customers` and `logs` tables only. Login/auth now lives on employees
 * (see apps/Employees/migrations) — there is no separate users table.
 * auth_identities is created in its own later migration
 * (create_auth_identities) since it needs the employees table to exist
 * first for its foreign key. signup_otps and login_throttles are owned
 * by their own pre-existing migrations (create_signup_otps_table,
 * add_attempts_to_signup_otps, create_login_throttles_table) and are not
 * created here.
 */
class CreateCustomersSchema extends AbstractMigration
{
    public function change(): void
    {
        $customers = $this->table('customers');
        $customers
            ->addColumn('company_name', 'string', ['limit' => 150])
            ->addColumn('customer_type', 'string', ['limit' => 20, 'default' => 'individual'])
            ->addColumn('phone', 'string', ['limit' => 20])
            ->addColumn('province', 'string', ['limit' => 50])
            ->addColumn('city', 'string', ['limit' => 100])
            ->addColumn('employee_count_range', 'string', ['limit' => 20])
            // Compliance fields, filled in during a post-signup setup wizard —
            // most small owners won't have these memorized at signup time.
            ->addColumn('ntn', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('eobi_registration_no', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('pessi_registration_no', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('business_type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->create();

        $logs = $this->table('logs');
        $logs
            ->addColumn('level', 'string', ['limit' => 20])
            ->addColumn('source', 'string', ['limit' => 100])
            ->addColumn('message', 'text')
            ->addColumn('context', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['level'])
            ->addIndex(['created_at'])
            ->create();
    }
}
