<?php

namespace App\Tests\Customers;

use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
use App\Utilities\CustomerManager;
use App\Utilities\DatabaseManager;
use App\Utilities\FlashManager;
use App\Utilities\ValidationManager;
use PHPUnit\Framework\TestCase;

class CustomerManagerTest extends TestCase
{
    use SeedsBillingSchema;
    use BuildsCustomerRegistrationData;

    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->seedBillingSchema($pdo);
        $_SESSION = [];
    }

    protected function tearDown(): void
    {
        // Never leave the real HIBP checker active for later tests.
        ValidationManager::setPwnedChecker(fn (string $password): bool => false);
    }

    public function test_register_creates_a_customer_and_its_first_user(): void
    {
        $result = CustomerManager::register($this->validRegistrationData());

        $this->assertTrue($result['success']);
        $this->assertSame('Acme Inc', $result['customer']->company_name);
        $this->assertSame('03001234567', $result['customer']->phone);
        $this->assertSame('punjab', $result['customer']->province);
        $this->assertSame($result['customer']->id, $result['user']->customer_id);
        $this->assertSame('03001234567', $result['user']->phone);
        $this->assertSame('owner', $result['user']->role);
        $this->assertTrue(password_verify('correct horse battery staple', $result['user']->password_hash));
    }

    public function test_register_also_starts_a_trial_subscription(): void
    {
        $result = CustomerManager::register($this->validRegistrationData());

        $this->assertSame($result['customer']->id, $result['subscription']->customer_id);
        $this->assertSame('trial', $result['subscription']->status);
        $this->assertNotNull($result['subscription']->trial_ends_at);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        CustomerManager::register($this->validRegistrationData());

        $result = CustomerManager::register($this->validRegistrationData(['company_name' => 'Other Inc']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function test_register_rejects_missing_required_fields(): void
    {
        $result = CustomerManager::register([]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('phone', $result['errors']);
        $this->assertArrayHasKey('province', $result['errors']);
    }

    public function test_register_rejects_an_invalid_phone_number(): void
    {
        $result = CustomerManager::register($this->validRegistrationData(['phone' => '12345']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('phone', $result['errors']);
    }

    public function test_register_rejects_a_weak_password(): void
    {
        $result = CustomerManager::register($this->validRegistrationData(['password' => 'password123']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function test_register_succeeds_with_a_pwned_password_but_adds_a_flash_warning(): void
    {
        ValidationManager::setPwnedChecker(fn (string $password): bool => true);

        $result = CustomerManager::register($this->validRegistrationData());

        $this->assertTrue($result['success']);

        $flashes = FlashManager::consume();
        $this->assertCount(1, $flashes);
        $this->assertSame('warning', $flashes[0]['type']);
        $this->assertStringContainsString('known data breach', $flashes[0]['message']);
    }
}
