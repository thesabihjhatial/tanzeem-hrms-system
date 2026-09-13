<?php

use Phinx\Migration\AbstractMigration;

/**
 * Not used by any flow yet — a landing place for OAuth provider links
 * (Google, Microsoft, etc.) once that work starts, so it doesn't need
 * its own schema migration then. Points at employees.id now that
 * login identity lives there instead of a separate users table.
 */
class CreateAuthIdentities extends AbstractMigration
{
    public function change(): void
    {
        $authIdentities = $this->table('auth_identities');
        $authIdentities
            ->addColumn('employee_id', 'integer', ['signed' => false])
            ->addColumn('provider', 'string', ['limit' => 50])
            ->addColumn('provider_user_id', 'string', ['limit' => 255])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['provider', 'provider_user_id'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
