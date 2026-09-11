<?php

namespace App\Tests\Billing;

use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
use App\Utilities\CustomerManager;
use App\Utilities\DatabaseManager;
use App\Utilities\SubscriptionManager;
use PHPUnit\Framework\TestCase;

class SubscriptionManagerTest extends TestCase
{
    use SeedsBillingSchema;
    use BuildsCustomerRegistrationData;

    private int $customerId;

    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->seedBillingSchema($pdo);

        $result = CustomerManager::register($this->validRegistrationData());
        $this->customerId = $result['customer']->id;
    }

    public function test_can_add_employee_is_true_under_the_plan_limit(): void
    {
        $this->assertTrue(SubscriptionManager::canAddEmployee($this->customerId));
    }

    public function test_can_add_employee_is_false_once_the_plan_limit_is_reached(): void
    {
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);

        for ($i = 0; $i < 10; $i++) {
            $pdo->exec("INSERT INTO employees (customer_id, first_name, last_name, email, created_at)
                VALUES ({$this->customerId}, 'First{$i}', 'Last{$i}', 'employee{$i}@acme.test', '2026-01-01 00:00:00')");
        }

        $this->assertFalse(SubscriptionManager::canAddEmployee($this->customerId));
    }

    public function test_can_add_employee_is_false_for_a_customer_with_no_subscription(): void
    {
        $this->assertFalse(SubscriptionManager::canAddEmployee(999));
    }
}
