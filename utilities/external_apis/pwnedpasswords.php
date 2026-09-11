<?php

// Pwned Passwords API added by Sabih

namespace App\Utilities\ExternalApis;

const PWNED_RANGE_URL = 'https://api.pwnedpasswords.com/range/';

const PWNED_REQUEST_TIMEOUT_SECONDS = 3;

function is_password_pwned(string $password, ?callable $fetcher = null): bool
{

    $fetcher ??= __NAMESPACE__ . '\\fetch_pwned_range';
    $sha1 = strtoupper(sha1($password));
    $prefix = substr($sha1, 0, 5);
    $suffix = substr($sha1, 5);
    $response = $fetcher($prefix);

    if ($response === null) {

        return false;

    }

    foreach (preg_split('/\r\n|\n/', trim($response)) as $line) {

        [$lineSuffix] = explode(':', $line, 2);

        if (strcasecmp($lineSuffix, $suffix) === 0) {

            return true;

        }

    }

    return false;

}

function fetch_pwned_range(string $prefix): ?string
{

    $context = stream_context_create([

        'http' => [

            'timeout' => PWNED_REQUEST_TIMEOUT_SECONDS,
            'ignore_errors' => true,
            
        ],

    ]);

    $response = @file_get_contents(PWNED_RANGE_URL . $prefix, false, $context);

    return $response === false ? null : $response;

}
