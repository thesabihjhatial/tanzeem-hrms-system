<?php

use Phinx\Migration\AbstractMigration;

/**
 * customer_type distinguishes an "individual" signup from a "company"
 * one (see signup.twig's toggle). id_number replaces the old cnic-only
 * column on users — it holds a CNIC for an individual owner or an NTN
 * for a company owner, decided by customer_type at signup time.
 */
class AddCustomerTypeAndIdNumber extends AbstractMigration
{
    public function change(): void
    {
        $this->table('customers')
            ->addColumn('customer_type', 'string', ['limit' => 20, 'default' => 'individual', 'after' => 'company_name'])
            ->update();

        $this->table('users')
            ->renameColumn('cnic', 'id_number')
            ->update();
    }
}
