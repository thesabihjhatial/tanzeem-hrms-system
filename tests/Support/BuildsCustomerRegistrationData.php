<?php

namespace App\Tests\Support;

trait BuildsCustomerRegistrationData
{
    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    protected function validRegistrationData(array $overrides = []): array
    {
        return array_merge([
            'company_name' => 'Acme Inc',
            'name' => 'Ada Lovelace',
            'email' => 'ada@acme.test',
            'phone' => '03001234567',
            'password' => 'correct horse battery staple',
            'province' => 'punjab',
            'city' => 'Lahore',
            'employee_count_range' => '1-10',
        ], $overrides);
    }
}
