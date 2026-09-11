<?php

use Phinx\Migration\AbstractMigration;

/**
 * Consolidated baseline for the Billing app's schema (previously three
 * separate migrations — plans, subscriptions, invoices — now squashed
 * into one; see CreateCustomersSchema for why).
 */
class CreateBillingSchema extends AbstractMigration
{
    public function change(): void
    {
        $plans = $this->table('plans');
        $plans
            ->addColumn('name', 'string', ['limit' => 100])
            ->addColumn('price', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('employee_limit', 'integer', ['signed' => false])
            ->addColumn('billing_cycle', 'string', ['limit' => 20, 'default' => 'monthly'])
            ->addColumn('is_default', 'boolean', ['default' => false])
            ->addColumn('created_at', 'datetime')
            ->create();

        $subscriptions = $this->table('subscriptions');
        $subscriptions
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('plan_id', 'integer', ['signed' => false])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'trial'])
            ->addColumn('trial_ends_at', 'datetime', ['null' => true])
            ->addColumn('current_period_end', 'datetime', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('plan_id', 'plans', 'id', ['delete' => 'RESTRICT'])
            ->create();

        $invoices = $this->table('invoices');
        $invoices
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('subscription_id', 'integer', ['signed' => false])
            ->addColumn('amount', 'decimal', ['precision' => 10, 'scale' => 2])
            ->addColumn('status', 'string', ['limit' => 20, 'default' => 'unpaid'])
            ->addColumn('issued_at', 'datetime')
            ->addColumn('paid_at', 'datetime', ['null' => true])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->addForeignKey('subscription_id', 'subscriptions', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
