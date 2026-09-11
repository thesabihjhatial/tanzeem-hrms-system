<?php

use Phinx\Migration\AbstractMigration;

/**
 * Consolidated baseline for the Customers app's schema (previously four
 * separate migrations — customers, users, auth_identities, logs — now
 * squashed into one, since a fresh install imports sql/schema.sql
 * directly rather than replaying history). auth_identities isn't wired
 * to any OAuth flow yet; it's a landing place for that future work.
 */
class CreateCustomersSchema extends AbstractMigration
{
    public function change(): void
    {
        $customers = $this->table('customers');
        $customers
            ->addColumn('company_name', 'string', ['limit' => 150])
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

        $users = $this->table('users');
        $users
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('phone', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('role', 'string', ['limit' => 20, 'default' => 'owner'])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['email'], ['unique' => true])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->create();

        $authIdentities = $this->table('auth_identities');
        $authIdentities
            ->addColumn('user_id', 'integer', ['signed' => false])
            ->addColumn('provider', 'string', ['limit' => 50])
            ->addColumn('provider_user_id', 'string', ['limit' => 255])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['provider', 'provider_user_id'], ['unique' => true])
            ->addForeignKey('user_id', 'users', 'id', ['delete' => 'CASCADE'])
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
