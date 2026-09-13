<?php

use Phinx\Migration\AbstractMigration;

class AddAttemptsToOtps extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('otps');
        $table
            ->addColumn('attempts', 'integer', ['signed' => false, 'default' => 0, 'after' => 'otp_hash'])
            ->update();
    }
}
