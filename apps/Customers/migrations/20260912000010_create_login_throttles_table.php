<?php

use Phinx\Migration\AbstractMigration;

/**
 * Backs login brute-force protection — one row per email attempted
 * (whether or not that email actually has an account, so a lockout
 * never reveals which emails exist), locking out further attempts for
 * a cooldown window once too many wrong passwords are seen in a row.
 */
class CreateLoginThrottlesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('login_throttles');
        $table
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('attempts', 'integer', ['signed' => false, 'default' => 0])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime')
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
