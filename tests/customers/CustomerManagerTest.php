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
        $result = $this->registerCustomer();

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
        $result = $this->registerCustomer();

        $this->assertSame($result['customer']->id, $result['subscription']->customer_id);
        $this->assertSame('trial', $result['subscription']->status);
        $this->assertNotNull($result['subscription']->trial_ends_at);
    }

    public function test_register_rejects_a_duplicate_email(): void
    {
        $this->registerCustomer();

        $result = CustomerManager::startRegistration($this->validRegistrationData(['company_name' => 'Other Inc']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('email', $result['errors']);
    }

    public function test_register_rejects_missing_required_fields(): void
    {
        // A valid form_token is included so the submission-timing guard
        // doesn't short-circuit before field validation runs — that guard
        // has its own dedicated coverage elsewhere.
        $result = CustomerManager::startRegistration([
            'form_token' => \App\Utilities\CryptographyManager::encrypt((string) (time() - 10)),
        ]);

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('name', $result['errors']);
        $this->assertArrayHasKey('phone', $result['errors']);
        $this->assertArrayHasKey('province', $result['errors']);
    }

    public function test_register_rejects_an_invalid_phone_number(): void
    {
        $result = CustomerManager::startRegistration($this->validRegistrationData(['phone' => '12345']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('phone', $result['errors']);
    }

    public function test_register_rejects_a_weak_password(): void
    {
        $result = CustomerManager::startRegistration($this->validRegistrationData(['password' => 'password123']));

        $this->assertFalse($result['success']);
        $this->assertArrayHasKey('password', $result['errors']);
    }

    public function test_register_succeeds_with_a_pwned_password_without_a_flash_warning(): void
    {
        ValidationManager::setPwnedChecker(fn (string $password): bool => true);

        $result = $this->registerCustomer();

        $this->assertTrue($result['success']);

        // The pwned-password notice is a soft warning surfaced live on the
        // signup form (see validation.js's checkPwned/setWarning) — it
        // must never also arrive as a toast on a later page.
        $this->assertCount(0, FlashManager::consume());
    }

    public function test_confirm_registration_rejects_an_incorrect_otp(): void
    {
        CustomerManager::startRegistration($this->validRegistrationData());

        $result = CustomerManager::confirmRegistration('000000');

        $this->assertFalse($result['success']);
        $this->assertSame('Incorrect verification code.', $result['error']);
    }

    public function test_confirm_registration_locks_after_three_incorrect_attempts(): void
    {
        CustomerManager::startRegistration($this->validRegistrationData());

        CustomerManager::confirmRegistration('000000');
        CustomerManager::confirmRegistration('111111');
        $result = CustomerManager::confirmRegistration('222222');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Too many incorrect', $result['error']);

        // The row is gone, so even the real code (had we known it) no
        // longer works — a fresh signup is required, not just a retry.
        $this->assertFalse(CustomerManager::hasPendingRegistration());
    }

    public function test_confirm_registration_rejects_an_expired_otp(): void
    {
        CustomerManager::startRegistration($this->validRegistrationData());

        DatabaseManager::execute(
            "UPDATE signup_otps SET expires_at = datetime('now', '-1 hour') WHERE email = ?",
            ['ada@acme.test'],
        );

        $result = CustomerManager::confirmRegistration('123456');

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('expired', $result['error']);
    }

    public function test_finalize_registration_rejects_an_invalid_plan(): void
    {
        CustomerManager::startRegistration($this->validRegistrationData());
        CustomerManager::confirmRegistration('123456');

        $result = CustomerManager::finalizeRegistration(999999);

        $this->assertFalse($result['success']);
        $this->assertFalse(isset($result['customer']));
    }

    public function test_starting_a_new_registration_clears_a_stale_verified_flag(): void
    {
        // Verify identity for one email, but never finalize (never pick a
        // plan) — SESSION_VERIFIED_KEY stays true, as it would for a real
        // user who clicks away from the plan page instead of choosing one.
        CustomerManager::startRegistration($this->validRegistrationData(['email' => 'first@acme.test']));
        CustomerManager::confirmRegistration('123456');
        $this->assertTrue(CustomerManager::hasVerifiedPendingRegistration());

        // Starting a second, unrelated signup must never inherit that
        // verified state — otherwise it could skip OTP verification
        // entirely for a completely different email address.
        CustomerManager::startRegistration($this->validRegistrationData(['email' => 'second@acme.test']));

        $this->assertFalse(CustomerManager::hasVerifiedPendingRegistration());
    }
}
