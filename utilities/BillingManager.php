<?php

// Tanzeem HRMS System Billing Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Invoice;
use App\Apps\Billing\Models\Plan;
use App\Apps\Billing\Models\Subscription;

class BillingManager
{

    // Maps a signup employee_count_range straight to the plan built for
    // it, so the post-signup plan-selection page can badge the one
    // matching what the customer already told us about their company.
    private const PLAN_BY_EMPLOYEE_RANGE = [
        '1-10' => 'Starter',
        '11-25' => 'Growth',
        '26-50' => 'Business',
    ];

    public const STATUS_PAID = 'paid';

    public const STATUS_UNPAID = 'unpaid';

    /** @return array<int, array{plan: Plan, recommended: bool}> */
    public static function listPlansForSelection(string $employeeCountRange): array
    {

        $recommendedName = self::PLAN_BY_EMPLOYEE_RANGE[$employeeCountRange] ?? null;

        return array_map(
            static fn (Plan $plan): array => ['plan' => $plan, 'recommended' => $plan->name === $recommendedName],
            Plan::all(),
        );

    }

    public static function generateInvoice(int $customerId, int $subscriptionId, float $amount): Invoice
    {

        return Invoice::create([
            'customer_id' => $customerId,
            'subscription_id' => $subscriptionId,
            'amount' => $amount,
            'status' => self::STATUS_UNPAID,
        ]);

    }

    public static function markPaid(int $invoiceId): bool
    {

        $invoice = Invoice::find($invoiceId);

        if ($invoice === null) {

            return false;

        }

        Invoice::markPaid($invoiceId);
        Subscription::updateStatus($invoice->subscription_id, SubscriptionManager::STATUS_ACTIVE);

        return true;

    }

    public static function invoicesForCustomer(int $customerId): array
    {

        return Invoice::forCustomer($customerId);

    }

}
