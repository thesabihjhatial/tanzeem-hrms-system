<?php

// Tanzeem HRMS System Validation Manager developed and maintained by Sabih
// All user input is validated and sanitized to ensure integrity and prevent vulnerabilities

namespace App\Utilities;

use function App\Utilities\ExternalApis\is_password_pwned;

class ValidationManager
{

    private const COMMON_PASSWORD_PATTERNS = '/(12345|password|qwerty|abc123|123456789)/i';

    private const DISPOSABLE_EMAIL_DOMAINS = [

        '10minutemail.com',
        'dispostable.com',
        'fakeinbox.com',
        'getnada.com',
        'guerrillamail.com',
        'maildrop.cc',
        'mailinator.com',
        'sharklasers.com',
        'temp-mail.org',
        'tempmail.com',
        'throwawaymail.com',
        'trashmail.com',
        'yopmail.com'
        
    ];

    private const PASSWORD_MAX_LENGTH = 128;

    private const SAFE_TEXT_PATTERN = '/^[a-zA-Z0-9\s.,\'&\-]+$/';

    /** @var ?callable(string): bool */
    private static $pwnedChecker = null;

    public static function setPwnedChecker(?callable $checker): void
    {

        self::$pwnedChecker = $checker;

    }

    /** @param array<string, mixed> $data @param array<string, array<int, string|array{0: string, 1: mixed}>> $rules @return array<string, string> */
    public static function validate(array $data, array $rules): array
    {

        $errors = [];

        foreach ($rules as $field => $fieldRules) {

            $value = $data[$field] ?? '';

            foreach ($fieldRules as $rule) {

                $error = self::applyRule($value, $rule);

                if ($error !== null) {

                    $errors[$field] = $error;

                    break;

                }

            }

        }

        return $errors;

    }

    public static function isDisposableEmailDomain(string $email): bool
    {

        $domain = strtolower(trim(substr((string) strrchr($email, '@'), 1)));

        return $domain !== '' && in_array($domain, self::DISPOSABLE_EMAIL_DOMAINS, true);

    }

    public static function isMonotonous(string $value): bool
    {

        $stripped = preg_replace('/\s/', '', $value);

        return $stripped !== '' && count(array_unique(str_split(strtolower($stripped)))) === 1;

    }

    public static function isValidCnic(string $cnic): bool
    {

        $normalized = preg_replace('/[\s\-]/', '', $cnic);

        return (bool) preg_match('/^\d{13}$/', $normalized) && !self::isMonotonous($normalized);

    }

    public static function isValidNtn(string $ntn): bool
    {

        $normalized = strtoupper(preg_replace('/[\s\-]/', '', $ntn));

        return (bool) preg_match('/^[A-Z0-9]{7,8}$/', $normalized) && !self::isMonotonous($normalized);

    }

    public static function isValidPakistaniPhone(string $phone): bool
    {

        $normalized = preg_replace('/[\s\-]/', '', $phone);

        if (!preg_match('/^(?:\+92|0092|0)3\d{9}$/', $normalized)) {

            return false;

        }

        return !self::isMonotonous(substr($normalized, -9));

    }

    public static function isPasswordPwned(string $password): bool
    {

        $checker = self::$pwnedChecker ?? is_password_pwned(...);

        return $checker($password);

    }

    /** @return array{errors: array<int, string>, warnings: array<int, string>} */
    public static function checkPasswordStrength(string $password, string $email = ''): array
    {

        $errors = [];

        if (strlen($password) > self::PASSWORD_MAX_LENGTH) {

            $errors[] = 'The password is too long.';

        }

        if (str_contains($password, "\n") || str_contains($password, "\r")) {

            $errors[] = 'Password cannot contain line breaks.';

        }

        if (preg_match(self::COMMON_PASSWORD_PATTERNS, $password)) {

            $errors[] = 'Avoid using common or easily guessable passwords.';

        }

        $emailLocalPart = $email !== '' ? strtolower(explode('@', $email)[0]) : '';

        if ($emailLocalPart !== '' && str_contains(strtolower($password), $emailLocalPart)) {

            $errors[] = 'Password cannot contain your email.';

        }

        $warnings = [];

        if ($errors === [] && self::isPasswordPwned($password)) {

            $warnings[] = 'This password was found compromised. Consider using a different password.';

        }

        return ['errors' => $errors, 'warnings' => $warnings];

    }

    private static function applyRule(mixed $value, string|array $rule): ?string
    {

        $value = is_string($value) ? trim($value) : $value;
        [$name, $param] = is_array($rule) ? $rule : [$rule, null];

        return match (true) {

            $name === 'required' && $value === '' => 'Field is required.',
            $name === 'email' && $value !== '' && !filter_var($value, FILTER_VALIDATE_EMAIL) => 'Enter a valid email address.',
            $name === 'not_disposable_email' && $value !== '' && self::isDisposableEmailDomain((string) $value) => 'Please use permanent email address, not a temporary one.',
            $name === 'phone_pk' && $value !== '' && !self::isValidPakistaniPhone((string) $value) => 'Enter a valid Pakistani number, e.g. 03001234567 or +923001234567.',
            $name === 'cnic_pk' && $value !== '' && !self::isValidCnic((string) $value) => 'Enter a valid 13-digit CNIC, e.g. 12345-1234567-1.',
            $name === 'ntn_pk' && $value !== '' && !self::isValidNtn((string) $value) => 'Enter a valid NTN number, e.g. 1234567-8.',
            is_string($name) && str_starts_with($name, 'min:') && $value !== '' && strlen((string) $value) < (int) substr($name, 4) => 'Must be least ' . substr($name, 4) . ' characters.',
            $name === 'in' && $value !== '' && !in_array($value, $param ?? [], true) => 'Please select a valid option.',
            $name === 'safe_text' && $value !== '' && !preg_match(self::SAFE_TEXT_PATTERN, (string) $value) => 'Contains characters that aren\'t allowed.',
            $name === 'has_letter' && $value !== '' && !preg_match('/[a-zA-Z]/', (string) $value) => 'Must contain least one letter.',
            $name === 'not_monotonous' && $value !== '' && self::isMonotonous((string) $value) => 'Please enter a real value.',
            default => null,

        };

    }

}
