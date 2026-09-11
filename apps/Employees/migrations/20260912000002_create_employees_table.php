<?php

use Phinx\Migration\AbstractMigration;

class CreateEmployeesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employees');
        $table
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['customer_id', 'email'], ['unique' => true])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
