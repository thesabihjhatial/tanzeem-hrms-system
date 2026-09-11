<?php

// Tanzeem HRMS System Validation Manager developed and maintained by Sabih

namespace App\Utilities;

use function App\Utilities\ExternalApis\is_password_pwned;

class ValidationManager
{

    private const COMMON_PASSWORD_PATTERNS = '/(12345|password|qwerty|abc123|123456789)/i';

    private const PASSWORD_MAX_LENGTH = 128;

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

    public static function isValidPakistaniPhone(string $phone): bool
    {

        $normalized = preg_replace('/[\s\-]/', '', $phone);

        return (bool) preg_match('/^(?:\+92|0092|0)3\d{9}$/', $normalized);

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

            $warnings[] = 'This password has appeared in a known data breach. Consider using a different password.';

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
            $name === 'phone_pk' && $value !== '' && !self::isValidPakistaniPhone((string) $value) => 'Enter a valid Pakistani number, e.g. 03001234567 or +923001234567.',
            is_string($name) && str_starts_with($name, 'min:') && $value !== '' && strlen((string) $value) < (int) substr($name, 4) => 'Must be least ' . substr($name, 4) . ' characters.',
            $name === 'in' && $value !== '' && !in_array($value, $param ?? [], true) => 'Please select a valid option.',
            default => null,

        };

    }

}
