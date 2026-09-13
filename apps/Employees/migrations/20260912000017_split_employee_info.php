<?php

use Phinx\Migration\AbstractMigration;

/**
 * Separates general HR/identity info from `employees`, which now holds
 * only auth-critical fields (login email, password, role) plus the
 * employee_id label. Name and id_number (CNIC/NTN) move here too — they're
 * HR-owned identity data, not something the auth/session hot path reads.
 * No separate `email` here: `employees.email` is the one email an
 * employee has, and it's already required and globally unique there.
 */
class SplitEmployeeInfo extends AbstractMigration
{
    public function up(): void
    {
        $this->table('employee_info')
            ->addColumn('employee_id', 'integer', ['signed' => false])
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('designation', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('id_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['employee_id'], ['unique' => true])
            ->addIndex(['id_number'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();

        $this->execute(
            "INSERT INTO employee_info (employee_id, first_name, last_name, department, designation, phone, city, id_number, created_at)
             SELECT id, first_name, last_name, department, designation, phone, city, id_number, NOW() FROM employees",
        );

        $this->table('employees')
            ->removeColumn('first_name')
            ->removeColumn('last_name')
            ->removeColumn('department')
            ->removeColumn('designation')
            ->removeColumn('phone')
            ->removeColumn('city')
            ->removeColumn('id_number')
            ->update();
    }

    public function down(): void
    {
        $this->table('employees')
            ->addColumn('first_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('last_name', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('designation', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('id_number', 'string', ['limit' => 50, 'null' => true])
            ->update();

        $this->execute(
            "UPDATE employees e
             JOIN employee_info ei ON ei.employee_id = e.id
             SET e.first_name = ei.first_name, e.last_name = ei.last_name, e.department = ei.department,
                 e.designation = ei.designation, e.phone = ei.phone, e.city = ei.city, e.id_number = ei.id_number",
        );

        $this->table('employees')
            ->changeColumn('first_name', 'string', ['limit' => 100, 'null' => false])
            ->changeColumn('last_name', 'string', ['limit' => 100, 'null' => false])
            ->addIndex(['id_number'], ['unique' => true])
            ->update();

        $this->table('employee_info')->drop()->save();
    }
}
