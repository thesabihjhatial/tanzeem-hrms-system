<?php

namespace App\Tests\Utilities;

use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
use App\Utilities\AuthenticationManager;
use App\Utilities\CustomerManager;
use App\Utilities\DatabaseManager;
use PHPUnit\Framework\TestCase;

class AuthenticationManagerTest extends TestCase
{
    use SeedsBillingSchema;
    use BuildsCustomerRegistrationData;

    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->seedBillingSchema($pdo);

        $_SESSION = [];
        CustomerManager::register($this->validRegistrationData());
        $_SESSION = [];
    }

    public function test_attempt_returns_the_user_on_correct_credentials(): void
    {
        $user = AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple');

        $this->assertNotNull($user);
        $this->assertSame('ada@acme.test', $user->email);
    }

    public function test_attempt_returns_null_on_wrong_password(): void
    {
        $this->assertNull(AuthenticationManager::attempt('ada@acme.test', 'wrong password'));
    }

    public function test_attempt_returns_null_for_unknown_email(): void
    {
        $this->assertNull(AuthenticationManager::attempt('nobody@acme.test', 'anything'));
    }

    public function test_login_populates_the_session(): void
    {
        $user = AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple');
        AuthenticationManager::login($user);

        $this->assertTrue(AuthenticationManager::check());
        $this->assertSame($user->id, AuthenticationManager::userId());
        $this->assertSame($user->customer_id, AuthenticationManager::customerId());
    }

    public function test_attempt_login_is_true_and_logs_in_on_success(): void
    {
        $this->assertTrue(AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple'));
        $this->assertTrue(AuthenticationManager::check());
    }

    public function test_attempt_login_is_false_and_does_not_log_in_on_failure(): void
    {
        $this->assertFalse(AuthenticationManager::attemptLogin('ada@acme.test', 'wrong password'));
        $this->assertFalse(AuthenticationManager::check());
    }

    public function test_guard_returns_null_when_authenticated(): void
    {
        AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple');

        $this->assertNull(AuthenticationManager::guard());
    }

    public function test_guard_returns_a_redirect_when_not_authenticated(): void
    {
        $response = AuthenticationManager::guard();

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }
}
