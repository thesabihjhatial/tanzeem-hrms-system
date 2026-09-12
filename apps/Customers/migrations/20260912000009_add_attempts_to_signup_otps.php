<?php

use Phinx\Migration\AbstractMigration;

class AddAttemptsToSignupOtps extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('signup_otps');
        $table
            ->addColumn('attempts', 'integer', ['signed' => false, 'default' => 0, 'after' => 'otp_hash'])
            ->update();
    }
}
