<?php

use Phinx\Migration\AbstractMigration;

/**
 * Adds the three paid tiers shown on the post-signup plan-selection page,
 * and turns the existing default plan into a real "Free" tier (it was
 * seeded as "Starter Trial" back when every signup only ever got one
 * plan). All four are monthly-only for now — yearly/3-year cycles are a
 * later addition, not scaffolded here.
 */
class AddPricingPlans extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->execute("UPDATE plans SET name = 'Free', employee_limit = 3 WHERE is_default = 1");

        $this->table('plans')->insert([
            [
                'name' => 'Starter',
                'price' => 2500,
                'employee_limit' => 10,
                'billing_cycle' => 'monthly',
                'is_default' => 0,
                'created_at' => $now,
            ],
            [
                'name' => 'Growth',
                'price' => 5000,
                'employee_limit' => 25,
                'billing_cycle' => 'monthly',
                'is_default' => 0,
                'created_at' => $now,
            ],
            [
                'name' => 'Business',
                'price' => 10000,
                'employee_limit' => 50,
                'billing_cycle' => 'monthly',
                'is_default' => 0,
                'created_at' => $now,
            ],
        ])->saveData();
    }

    public function down(): void
    {
        $this->execute("DELETE FROM plans WHERE name IN ('Starter', 'Growth', 'Business')");
        $this->execute("UPDATE plans SET name = 'Starter Trial', employee_limit = 10 WHERE is_default = 1");
    }
}
