<?php

use Phinx\Migration\AbstractMigration;

class AddEmployeeDetails extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employees');
        $table
            ->addColumn('employee_id', 'string', ['limit' => 50, 'null' => true, 'after' => 'customer_id'])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true, 'after' => 'last_name'])
            ->addColumn('designation', 'string', ['limit' => 100, 'null' => true, 'after' => 'department'])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true, 'after' => 'email'])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true, 'after' => 'phone'])
            ->addIndex(['customer_id', 'employee_id'], ['unique' => true])
            ->update();
    }
}
