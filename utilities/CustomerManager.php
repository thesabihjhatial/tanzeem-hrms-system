<?php

// Tanzeem HRMS System Customer Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Customers\Models\Customer;
use App\Apps\Customers\Models\User;

class CustomerManager
{

    public const EMPLOYEE_COUNT_RANGES = [
        '1-10' => '1-10 employees',
        '11-25' => '11-25 employees',
        '26-50' => '26-50 employees',
    ];

    private const ERROR_EMAIL_TAKEN = 'This email is already registered.';

    private const ERROR_REGISTRATION_FAILED = 'Something went wrong.';

    private const LOG_SOURCE = 'CustomerManager';

    public const PROVINCES = [
        'ajk' => 'Azad Jammu & Kashmir',
        'balochistan' => 'Balochistan',
        'gb' => 'Gilgit-Baltistan',
        'ict' => 'Islamabad Capital Territory',
        'kpk' => 'Khyber Pakhtunkhwa',
        'punjab' => 'Punjab',
        'sindh' => 'Sindh',
    ];

    public const ROLE_OWNER = 'owner';

    /** @param array<string, mixed> $data @return array{success: bool, errors?: array<string, string>, customer?: Customer, user?: User, subscription?: \App\Apps\Billing\Models\Subscription} */
    public static function register(array $data): array
    {

        $errors = ValidationManager::validate($data, self::registrationRules());

        $passwordCheck = ['errors' => [], 'warnings' => []];

        if (!isset($errors['password'])) {

            $passwordCheck = ValidationManager::checkPasswordStrength($data['password'] ?? '', $data['email'] ?? '');

            if ($passwordCheck['errors'] !== []) {

                $errors['password'] = implode(' ', $passwordCheck['errors']);

            }

        }

        if ($errors !== []) {

            return ['success' => false, 'errors' => $errors];

        }

        if (self::findUserByEmail($data['email']) !== null) {

            return ['success' => false, 'errors' => ['email' => self::ERROR_EMAIL_TAKEN]];

        }

        DatabaseManager::beginTransaction();

        try {

            $customer = Customer::create([
                'company_name' => $data['company_name'],
                'phone' => $data['phone'],
                'province' => $data['province'],
                'city' => $data['city'],
                'employee_count_range' => $data['employee_count_range'],
            ]);

            $user = User::create([
                'customer_id' => $customer->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'password_hash' => password_hash($data['password'], PASSWORD_DEFAULT),
                'role' => self::ROLE_OWNER,
            ]);

            $subscription = SubscriptionManager::startTrial($customer->id);

            DatabaseManager::commit();

        } catch (\Throwable $e) {

            DatabaseManager::rollBack();
            LogManager::error(self::LOG_SOURCE, 'Registration failed: ' . $e->getMessage());

            return ['success' => false, 'errors' => ['email' => self::ERROR_REGISTRATION_FAILED]];

        }

        AuthenticationManager::login($user);

        foreach ($passwordCheck['warnings'] as $warning) {

            FlashManager::add($warning, 'warning');

        }

        return ['success' => true, 'customer' => $customer, 'user' => $user, 'subscription' => $subscription];

    }

    public static function findUserByEmail(string $email): ?User
    {

        return User::findByEmail($email);

    }

    /** @return array<string, array<int, string|array{0: string, 1: mixed}>> */
    private static function registrationRules(): array
    {

        return [
            'name' => ['required'],
            'company_name' => ['required'],
            'phone' => ['required', 'phone_pk'],
            'email' => ['required', 'email'],
            'password' => ['required', 'min:8'],
            'province' => ['required', ['in', array_keys(self::PROVINCES)]],
            'city' => ['required'],
            'employee_count_range' => ['required', ['in', array_keys(self::EMPLOYEE_COUNT_RANGES)]],
        ];

    }

}
