<?php

// Tanzeem HRMS System Dashboard Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Employees\Models\Employee;

class DashboardManager
{

    /** @return array<string, mixed> */
    public static function overviewForCustomer(int $customerId, int $userId): array
    {

        $employee = Employee::find($customerId, $userId);
        $subscription = SubscriptionManager::forCustomer($customerId);
        $plan = $subscription !== null ? Plan::find($subscription->plan_id) : null;

        return [
            'greeting' => self::greeting(),
            'user_name' => $employee !== null ? trim($employee->first_name . ' ' . $employee->last_name) : null,
            'you' => $employee !== null ? self::youProfile($employee) : null,
            'employee_count' => count(EmployeeManager::listForCustomer($customerId)),
            'employee_limit' => $plan?->employee_limit,
            'is_trial' => $subscription?->status === 'trial',
            'plan_name' => $plan?->name,
            'plan_billing_cycle' => $plan?->billing_cycle,
            'subscription_status' => $subscription?->status,
            'subscription_status_variant' => $subscription !== null ? SubscriptionManager::statusVariant($subscription->status) : null,
            'next_renewal_date' => $subscription?->current_period_end ?? $subscription?->trial_ends_at,
            'plan_price' => $plan?->price,
        ];

    }

    /** @return array{employee_id: ?string, name: string, department: ?string, designation: ?string, email: string, phone: ?string, city: ?string} */
    private static function youProfile(Employee $employee): array
    {

        return [
            'employee_id' => $employee->employee_id,
            'name' => trim($employee->first_name . ' ' . $employee->last_name),
            'department' => $employee->department,
            'designation' => $employee->designation,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'city' => $employee->city,
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
