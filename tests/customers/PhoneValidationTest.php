<?php

namespace App\Tests\Customers;

use App\Utilities\ValidationManager;
use PHPUnit\Framework\TestCase;

class PhoneValidationTest extends TestCase
{
    /** @dataProvider validPhones */
    public function test_accepts_valid_pakistani_phone_numbers(string $phone): void
    {
        $this->assertTrue(ValidationManager::isValidPakistaniPhone($phone));
    }

    /** @dataProvider invalidPhones */
    public function test_rejects_invalid_phone_numbers(string $phone): void
    {
        $this->assertFalse(ValidationManager::isValidPakistaniPhone($phone));
    }

    public static function validPhones(): array
    {
        return [
            ['03001234567'],
            ['+923001234567'],
            ['00923001234567'],
            ['0300 123 4567'],
            ['0300-1234567'],
        ];
    }

    public static function invalidPhones(): array
    {
        return [
            ['12345'],
            ['0300123456'],       // one digit short
            ['030012345678'],    // one digit too many
            ['+441234567890'],   // UK number
            ['02001234567'],     // landline prefix, not mobile
            ['abcdefghijk'],
        ];
    }
}
