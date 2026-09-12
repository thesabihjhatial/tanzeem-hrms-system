<?php

use Phinx\Migration\AbstractMigration;

/**
 * Backs the post-signup OTP step with a real table instead of only
 * $_SESSION — the OTP hash, the pending customer/user payload, and the
 * 15-minute expiry all live here, so a dropped session or a second app
 * server doesn't lose an in-progress signup. One row per email; a
 * fresh signup attempt for the same address replaces its row.
 */
class CreateSignupOtpsTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('signup_otps');
        $table
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('otp_hash', 'string', ['limit' => 255])
            ->addColumn('payload', 'text')
            ->addColumn('expires_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addIndex(['email'], ['unique' => true])
            ->create();
    }
}
