<?php

use Phinx\Migration\AbstractMigration;

class CreateEmployeesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employees');
        $table
            ->addColumn('uuid', 'string', ['limit' => 36])
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('employee_id', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('designation', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('role', 'string', ['limit' => 20, 'default' => 'viewer'])
            ->addColumn('id_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['uuid'], ['unique' => true])
            ->addIndex(['customer_id', 'employee_id'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['id_number'], ['unique' => true])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
