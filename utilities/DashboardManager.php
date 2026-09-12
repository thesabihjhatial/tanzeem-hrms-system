<?php

// Tanzeem HRMS System Dashboard Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Customers\Models\User;

class DashboardManager
{

    /** @return array<string, mixed> */
    public static function overviewForCustomer(int $customerId, int $userId): array
    {

        $user = User::find($userId);
        $subscription = SubscriptionManager::forCustomer($customerId);
        $plan = $subscription !== null ? Plan::find($subscription->plan_id) : null;

        return [
            'greeting' => self::greeting(),
            'user_name' => $user?->name,
            'employee_count' => count(EmployeeManager::listForCustomer($customerId)),
            'employee_limit' => $plan?->employee_limit,
            'plan_name' => $plan?->name,
            'subscription_status' => $subscription?->status,
            'trial_ends_at' => $subscription?->trial_ends_at,
        ];

    }

    private static function greeting(): string
    {

        $hour = (int) date('G');

        return match (true) {

            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',

        };

    }

}
