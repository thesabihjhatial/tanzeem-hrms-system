<?php

namespace App\Tests\Utilities;

use App\Utilities\ValidationManager;
use PHPUnit\Framework\TestCase;

class ValidationManagerTest extends TestCase
{
    protected function tearDown(): void
    {
        // Always restore the network-free stub, even if a test fails
        // partway through — never let this leak into other test files.
        ValidationManager::setPwnedChecker(fn (string $password): bool => false);
    }

    public function test_required_field_reports_error_when_missing(): void
    {
        $errors = ValidationManager::validate([], ['name' => ['required']]);

        $this->assertArrayHasKey('name', $errors);
    }

    public function test_required_field_passes_when_present(): void
    {
        $errors = ValidationManager::validate(['name' => 'Ada'], ['name' => ['required']]);

        $this->assertArrayNotHasKey('name', $errors);
    }

    public function test_email_rule_rejects_malformed_addresses(): void
    {
        $errors = ValidationManager::validate(['email' => 'not-an-email'], ['email' => ['email']]);

        $this->assertArrayHasKey('email', $errors);
    }

    public function test_min_rule_rejects_short_values(): void
    {
        $errors = ValidationManager::validate(['password' => 'short'], ['password' => ['min:8']]);

        $this->assertArrayHasKey('password', $errors);
    }

    public function test_min_rule_passes_long_enough_values(): void
    {
        $errors = ValidationManager::validate(['password' => 'longenough'], ['password' => ['min:8']]);

        $this->assertArrayNotHasKey('password', $errors);
    }

    public function test_in_rule_rejects_values_outside_the_allowed_list(): void
    {
        $errors = ValidationManager::validate(['province' => 'narnia'], ['province' => [['in', ['punjab', 'sindh']]]]);

        $this->assertArrayHasKey('province', $errors);
    }

    public function test_in_rule_accepts_a_listed_value(): void
    {
        $errors = ValidationManager::validate(['province' => 'sindh'], ['province' => [['in', ['punjab', 'sindh']]]]);

        $this->assertArrayNotHasKey('province', $errors);
    }

    public function test_stops_at_the_first_failing_rule_per_field(): void
    {
        $errors = ValidationManager::validate([], ['email' => ['required', 'email']]);

        $this->assertSame('Field is required.', $errors['email']);
    }

    public function test_password_strength_rejects_a_common_pattern(): void
    {
        $result = ValidationManager::checkPasswordStrength('password123');

        $this->assertNotEmpty($result['errors']);
    }

    public function test_password_strength_rejects_a_password_containing_the_email(): void
    {
        $result = ValidationManager::checkPasswordStrength('adalovelace99', 'adalovelace@acme.test');

        $this->assertContains('Password cannot contain your email.', $result['errors']);
    }

    public function test_password_strength_rejects_an_overly_long_password(): void
    {
        $result = ValidationManager::checkPasswordStrength(str_repeat('a', 129));

        $this->assertContains('The password is too long.', $result['errors']);
    }

    public function test_password_strength_accepts_a_reasonable_password(): void
    {
        $result = ValidationManager::checkPasswordStrength('a reasonably unique passphrase 9x', 'ada@acme.test');

        $this->assertEmpty($result['errors']);
    }

    public function test_pwned_password_is_a_warning_not_a_blocking_error(): void
    {
        ValidationManager::setPwnedChecker(fn (string $password): bool => true);

        $result = ValidationManager::checkPasswordStrength('a reasonably unique passphrase 9x');

        $this->assertEmpty($result['errors']);
        $this->assertNotEmpty($result['warnings']);
    }
}
