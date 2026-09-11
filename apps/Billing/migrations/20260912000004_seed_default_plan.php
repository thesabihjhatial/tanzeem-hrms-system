<?php

use Phinx\Migration\AbstractMigration;

class SeedDefaultPlan extends AbstractMigration
{
    public function up(): void
    {
        $this->table('plans')->insert([
            'name' => 'Starter Trial',
            'price' => 0,
            'employee_limit' => 10,
            'billing_cycle' => 'monthly',
            'is_default' => 1,
            'created_at' => date('Y-m-d H:i:s'),
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM plans WHERE name = 'Starter Trial'");
    }
}
