<?php

// Tanzeem HRMS System Billing Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Invoice;
use App\Apps\Billing\Models\Subscription;

class BillingManager
{

    public const STATUS_PAID = 'paid';

    public const STATUS_UNPAID = 'unpaid';

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
