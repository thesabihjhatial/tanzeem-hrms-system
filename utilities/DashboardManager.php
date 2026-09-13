<?php

// Tanzeem HRMS System Dashboard Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Employees\Models\Employee;
use App\Apps\Employees\Models\EmployeeInfo;

class DashboardManager
{

    /** @return array<string, mixed> */
    public static function overviewForCustomer(int $customerId, int $userId): array
    {

        $employee = Employee::find($customerId, $userId);
        $you = $employee !== null ? self::youProfile($employee) : null;
        $subscription = SubscriptionManager::forCustomer($customerId);
        $plan = $subscription !== null ? Plan::find($subscription->plan_id) : null;

        return [
            'greeting' => self::greeting(),
            'user_name' => $you['name'] ?? null,
            'you' => $you,
            'is_admin' => $employee?->role === EmployeeManager::ROLE_ADMIN,
            'employee_count' => count(EmployeeManager::listForCustomer($customerId)),
            'employee_limit' => $plan?->employee_limit,
            'employee_hires_this_month' => EmployeeManager::hiresThisMonth($customerId),
            'is_trial' => $subscription?->status === 'trial',
            'plan_name' => $plan?->name,
            'plan_billing_cycle' => $plan?->billing_cycle,
            'subscription_status' => $subscription?->status,
            'subscription_status_variant' => $subscription !== null ? SubscriptionManager::statusVariant($subscription->status) : null,
            'next_renewal_date' => $subscription?->current_period_end ?? $subscription?->trial_ends_at,
            'plan_price' => $plan?->price,
        ];

    }

    /** @return array{employee_id: ?string, uuid: string, name: string, department: ?string, designation: ?string, email: string, phone: ?string, city: ?string} */
    private static function youProfile(Employee $employee): array
    {

        $info = EmployeeInfo::findByEmployeeId($employee->id);

        return [
            'employee_id' => $employee->employee_id,
            'uuid' => $employee->uuid,
            'name' => $info !== null ? trim($info->first_name . ' ' . $info->last_name) : '',
            'department' => $info?->department,
            'designation' => $info?->designation,
            'email' => $employee->email,
            'phone' => $info?->phone,
            'city' => $info?->city,
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
