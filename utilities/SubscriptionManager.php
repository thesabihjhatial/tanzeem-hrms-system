<?php

// Tanzeem HRMS System Subscription Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Billing\Models\Subscription;

class SubscriptionManager
{

    public const DEFAULT_TRIAL_DAYS = 7;

    public const STATUS_ACTIVE = 'active';

    public const STATUS_TRIAL = 'trial';

    public static function startTrial(int $customerId, int $planId, int $trialDays = self::DEFAULT_TRIAL_DAYS): Subscription
    {

        return Subscription::create([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'status' => self::STATUS_TRIAL,
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime("+{$trialDays} days")),
            'current_period_end' => null,
        ]);

    }

    public static function forCustomer(int $customerId): ?Subscription
    {

        return Subscription::forCustomer($customerId);

    }

    public static function canAddEmployee(int $customerId): bool
    {

        $subscription = self::forCustomer($customerId);

        if ($subscription === null) {

            return false;

        }

        $plan = Plan::find($subscription->plan_id);

        if ($plan === null) {

            return false;

        }

        $row = DatabaseManager::selectOne('SELECT COUNT(*) AS count FROM employees WHERE customer_id = ?', [$customerId]);

        return (int) ($row['count'] ?? 0) < $plan->employee_limit;

    }

}
