<?php

namespace App\Tests\Utilities;

use PHPUnit\Framework\TestCase;

use function App\Utilities\ExternalApis\is_password_pwned;

class PwnedPasswordsTest extends TestCase
{
    public function test_returns_true_when_the_suffix_is_in_the_range_response(): void
    {
        $sha1 = strtoupper(sha1('password123'));
        $suffix = substr($sha1, 5);

        $fetcher = fn (string $prefix) => "{$suffix}:2000000\r\nAAAA1111BBBB2222CCCC3333DDDD4444EEE1:5";

        $this->assertTrue(is_password_pwned('password123', $fetcher));
    }

    public function test_returns_false_when_the_suffix_is_not_in_the_range_response(): void
    {
        $fetcher = fn (string $prefix) => "AAAA1111BBBB2222CCCC3333DDDD4444EEE1:5\r\nFFFF0000GGGG1111HHHH2222IIII3333JJJ2:1";

        $this->assertFalse(is_password_pwned('correct horse battery staple 42', $fetcher));
    }

    public function test_fails_open_when_the_fetcher_returns_null(): void
    {
        $fetcher = fn (string $prefix) => null;

        $this->assertFalse(is_password_pwned('anything', $fetcher));
    }

    public function test_prefix_passed_to_the_fetcher_is_the_first_five_sha1_hex_chars(): void
    {
        $expectedPrefix = strtoupper(substr(sha1('correct horse battery staple'), 0, 5));
        $seenPrefix = null;

        $fetcher = function (string $prefix) use (&$seenPrefix) {
            $seenPrefix = $prefix;

            return '';
        };

        is_password_pwned('correct horse battery staple', $fetcher);

        $this->assertSame($expectedPrefix, $seenPrefix);
    }
}
