<?php

use Phinx\Migration\AbstractMigration;

class AddCnicToUsers extends AbstractMigration
{
    public function change(): void
    {
        $users = $this->table('users');
        $users
            ->addColumn('cnic', 'string', ['limit' => 15, 'null' => true, 'after' => 'phone'])
            ->addIndex(['cnic'], ['unique' => true])
            ->update();
    }
}
