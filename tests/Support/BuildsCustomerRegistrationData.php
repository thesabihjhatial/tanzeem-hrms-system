<?php

namespace App\Tests\Support;

use App\Apps\Billing\Models\Plan;
use App\Utilities\CryptographyManager;
use App\Utilities\CustomerManager;

trait BuildsCustomerRegistrationData
{
    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    protected function validRegistrationData(array $overrides = []): array
    {
        $password = 'correct horse battery staple';

        return array_merge([
            'company_name' => 'Acme Inc',
            'name' => 'Ada Lovelace',
            'email' => 'ada@acme.test',
            'phone' => '03001234567',
            'cnic' => '3520112345671',
            'password' => $password,
            'confirm_password' => $password,
            'province' => 'punjab',
            'city' => 'Lahore',
            'employee_count_range' => '1-10',
            // Backdated so the submission-timing guard never trips in tests.
            'form_token' => CryptographyManager::encrypt((string) (time() - 10)),
        ], $overrides);
    }

    /**
     * Drives the full three-step signup flow (start, OTP confirm, plan
     * choice) in one call, for tests that only care about the end result
     * of a completed registration rather than any individual step —
     * the account itself isn't created until the plan step, matching
     * production behavior.
     *
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    protected function registerCustomer(array $overrides = []): array
    {
        $result = CustomerManager::startRegistration($this->validRegistrationData($overrides));

        if (!$result['success']) {
            return $result;
        }

        $verified = CustomerManager::confirmRegistration($result['otp']);

        if (!$verified['success']) {
            return $verified;
        }

        return CustomerManager::finalizeRegistration(Plan::default()->id);
    }
}
