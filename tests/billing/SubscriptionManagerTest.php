<?php

namespace App\Tests\Billing;

use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
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

        $_SESSION = [];
        $result = $this->registerCustomer();
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
            $uuid = sprintf('00000000-0000-0000-0000-%012d', $i);
            $pdo->exec("INSERT INTO employees (uuid, customer_id, email, created_at)
                VALUES ('{$uuid}', {$this->customerId}, 'employee{$i}@acme.test', '2026-01-01 00:00:00')");
        }

        $this->assertFalse(SubscriptionManager::canAddEmployee($this->customerId));
    }

    public function test_can_add_employee_is_false_for_a_customer_with_no_subscription(): void
    {
        $this->assertFalse(SubscriptionManager::canAddEmployee(999));
    }
}
