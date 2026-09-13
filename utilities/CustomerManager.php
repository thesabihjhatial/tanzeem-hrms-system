<?php

// Tanzeem HRMS System Customer Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Customers\Models\Customer;
use App\Apps\Customers\Models\SignupOtp;
use App\Apps\Employees\Models\Employee;

class CustomerManager
{

    private const DEV_OTP_BYPASS = '123456';

    public const EMPLOYEE_COUNT_RANGES = [
        '1-10' => '1-10 employees',
        '11-25' => '11-25 employees',
        '26-50' => '26-50 employees',
    ];

    private const ERROR_CNIC_TAKEN = 'This CNIC is already registered.';

    private const ERROR_EMAIL_TAKEN = 'This email is already registered.';

    private const ERROR_NTN_TAKEN = 'This NTN is already registered.';

    private const ERROR_OTP_EXPIRED = 'Your verification session has expired. Please start over.';

    private const ERROR_OTP_INCORRECT = 'Incorrect verification code.';

    private const ERROR_OTP_LOCKED = 'Too many incorrect verification attempts. Please start over.';

    private const ERROR_PASSWORD_MISMATCH = 'The passwords do not match.';

    private const ERROR_REGISTRATION_FAILED = 'Account creation failed. The incident has been logged for investigation.';

    private const LOG_SOURCE = 'CustomerManager';

    private const MAX_OTP_ATTEMPTS = 3;

    private const MIN_SUBMISSION_SECONDS = 3;

    private const OTP_LENGTH = 6;

    private const OTP_TTL_SECONDS = 900;

    public const PROVINCES = [
        'ajk' => 'Azad Jammu & Kashmir',
        'balochistan' => 'Balochistan',
        'gb' => 'Gilgit-Baltistan',
        'ict' => 'Islamabad Capital Territory',
        'kpk' => 'Khyber Pakhtunkhwa',
        'punjab' => 'Punjab',
        'sindh' => 'Sindh',
    ];

    private const SESSION_PENDING_EMAIL_KEY = 'pending_signup_email';

    private const SESSION_VERIFIED_KEY = 'pending_signup_verified';

    /** @param array<string, mixed> $data @return array{success: bool, errors?: array<string, string>, otp?: string} */
    public static function startRegistration(array $data): array
    {

        if (($data['tzm_hp'] ?? '') !== '') {

            LogManager::warning(self::LOG_SOURCE, 'Whoa there, genius.');

            return ['success' => false, 'errors' => ['email' => self::ERROR_REGISTRATION_FAILED]];

        }

        if (!self::wasSubmittedByAHuman($data['form_token'] ?? '')) {

            LogManager::warning(self::LOG_SOURCE, 'Whoa, slow down.');

            return ['success' => false, 'errors' => ['email' => self::ERROR_REGISTRATION_FAILED]];

        }

        $errors = ValidationManager::validate($data, self::registrationRules($data));

        if (!isset($errors['confirm_password']) && ($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {

            $errors['confirm_password'] = self::ERROR_PASSWORD_MISMATCH;

        }

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

        if (self::findEmployeeByEmail($data['email']) !== null) {

            return ['success' => false, 'errors' => ['email' => self::ERROR_EMAIL_TAKEN]];

        }

        $customerType = ($data['customer_type'] ?? 'individual') === 'company' ? 'company' : 'individual';

        if (self::findEmployeeByIdNumber($data['cnic']) !== null) {

            return ['success' => false, 'errors' => ['cnic' => $customerType === 'company' ? self::ERROR_NTN_TAKEN : self::ERROR_CNIC_TAKEN]];

        }

        $otp = self::generateOtp();

        $payload = [
            'customer' => [
                'company_name' => $data['company_name'],
                'customer_type' => $customerType,
                'phone' => $data['phone'],
                'province' => $data['province'],
                'city' => $data['city'],
                'employee_count_range' => $data['employee_count_range'],
            ],
            'employee' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'id_number' => $data['cnic'],
                'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            ],
        ];

        SignupOtp::create([
            'email' => $data['email'],
            'otp_hash' => password_hash($otp, PASSWORD_ARGON2ID),
            'payload' => json_encode($payload),
            'expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS),
        ]);

        unset($_SESSION[self::SESSION_VERIFIED_KEY]);
        $_SESSION[self::SESSION_PENDING_EMAIL_KEY] = $data['email'];

        return ['success' => true, 'otp' => $otp];

    }

    /** @return array{success: bool, error?: string} */
    public static function confirmRegistration(string $otp): array
    {

        $pendingOtp = self::currentPendingOtp();

        if ($pendingOtp === null) {

            return ['success' => false, 'error' => self::ERROR_OTP_EXPIRED];

        }

        if ($pendingOtp->attempts >= self::MAX_OTP_ATTEMPTS) {

            SignupOtp::deleteByEmail($pendingOtp->email);
            unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

            return ['success' => false, 'error' => self::ERROR_OTP_LOCKED];

        }

        $isDevBypass = $otp === self::DEV_OTP_BYPASS && !EnvironmentManager::isProduction();

        if (!$isDevBypass && !password_verify($otp, $pendingOtp->otp_hash)) {

            SignupOtp::incrementAttempts($pendingOtp->email);

            if ($pendingOtp->attempts + 1 >= self::MAX_OTP_ATTEMPTS) {

                SignupOtp::deleteByEmail($pendingOtp->email);
                unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

                return ['success' => false, 'error' => self::ERROR_OTP_LOCKED];

            }

            return ['success' => false, 'error' => self::ERROR_OTP_INCORRECT];

        }

        $_SESSION[self::SESSION_VERIFIED_KEY] = true;

        return ['success' => true];

    }

    /** @return array{success: bool, error?: string, customer?: Customer, employee?: Employee, subscription?: \App\Apps\Billing\Models\Subscription} */
    public static function finalizeRegistration(int $planId): array
    {

        $pendingOtp = self::currentVerifiedOtp();

        if ($pendingOtp === null) {

            return ['success' => false, 'error' => self::ERROR_OTP_EXPIRED];

        }

        if (Plan::find($planId) === null) {

            return ['success' => false, 'error' => 'Please choose a plan first.'];

        }

        $payload = json_decode($pendingOtp->payload, true);

        DatabaseManager::beginTransaction();

        try {

            $customer = Customer::create($payload['customer']);

            $employee = EmployeeManager::createAdminEmployee($customer->id, [
                'name' => $payload['employee']['name'],
                'email' => $payload['employee']['email'],
                'phone' => $payload['employee']['phone'],
                'city' => $payload['customer']['city'],
                'id_number' => $payload['employee']['id_number'],
                'password_hash' => $payload['employee']['password_hash'],
            ]);

            $subscription = SubscriptionManager::startTrial($customer->id, $planId);

            DatabaseManager::commit();

        } catch (\Throwable $e) {

            DatabaseManager::rollBack();
            LogManager::error(self::LOG_SOURCE, 'Registration failed: ' . $e->getMessage());

            return ['success' => false, 'error' => self::ERROR_REGISTRATION_FAILED];

        }

        SignupOtp::deleteByEmail($pendingOtp->email);
        unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);
        AuthenticationManager::login($employee);

        return ['success' => true, 'customer' => $customer, 'employee' => $employee, 'subscription' => $subscription];

    }

    public static function hasPendingRegistration(): bool
    {

        return self::currentPendingOtp() !== null;

    }

    public static function hasVerifiedPendingRegistration(): bool
    {

        return self::currentVerifiedOtp() !== null;

    }

    /** @return array<string, mixed>|null */
    public static function pendingSignupCustomerData(): ?array
    {

        $pendingOtp = self::currentVerifiedOtp();

        if ($pendingOtp === null) {

            return null;

        }

        $payload = json_decode($pendingOtp->payload, true);

        return $payload['customer'] ?? null;

    }

    public static function findEmployeeByEmail(string $email): ?Employee
    {

        return EmployeeManager::findByEmail($email);

    }

    public static function findEmployeeByIdNumber(string $idNumber): ?Employee
    {

        return EmployeeManager::findByIdNumber($idNumber);

    }

    public static function generateFormToken(): string
    {

        return CryptographyManager::encrypt((string) time());

    }

    private static function generateOtp(): string
    {

        return str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);

    }

    /** @param array<string, mixed> $data @return array<string, array<int, string|array{0: string, 1: mixed}>> */
    private static function registrationRules(array $data): array
    {

        $isCompany = ($data['customer_type'] ?? 'individual') === 'company';

        $nameRules = $isCompany
            ? ['required']
            : ['required', 'min:5', 'safe_text', 'has_letter', 'not_monotonous'];

        $cnicRules = $isCompany ? ['required', 'ntn_pk'] : ['required', 'cnic_pk'];

        return [
            'name' => $nameRules,
            'company_name' => ['required', 'min:3', 'safe_text', 'has_letter', 'not_monotonous'],
            'phone' => ['required', 'phone_pk'],
            'cnic' => $cnicRules,
            'email' => ['required', 'email', 'not_disposable_email'],
            'password' => ['required', 'min:8'],
            'confirm_password' => ['required'],
            'province' => ['required', ['in', array_keys(self::PROVINCES)]],
            'city' => ['required', 'min:2', 'safe_text', 'has_letter', 'not_monotonous'],
            'employee_count_range' => ['required', ['in', array_keys(self::EMPLOYEE_COUNT_RANGES)]],
        ];

    }

    private static function currentPendingOtp(): ?SignupOtp
    {

        $email = $_SESSION[self::SESSION_PENDING_EMAIL_KEY] ?? null;

        if ($email === null) {

            return null;

        }

        $pendingOtp = SignupOtp::findByEmail($email);

        if ($pendingOtp === null || $pendingOtp->isExpired()) {

            unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

            if ($pendingOtp !== null) {

                SignupOtp::deleteByEmail($email);

            }

            return null;

        }

        return $pendingOtp;

    }

    private static function currentVerifiedOtp(): ?SignupOtp
    {

        if (!($_SESSION[self::SESSION_VERIFIED_KEY] ?? false)) {

            return null;

        }

        return self::currentPendingOtp();

    }

    private static function wasSubmittedByAHuman(string $formToken): bool
    {

        try {

            $renderedAt = (int) CryptographyManager::decrypt($formToken);

        } catch (\Throwable) {

            return false;

        }

        return (time() - $renderedAt) >= self::MIN_SUBMISSION_SECONDS;

    }

}
