<?php

require __DIR__ . '/../vendor/autoload.php';

// The test suite must never depend on a live network call. The pwned-password
// matching logic itself is covered directly (with an injected fake fetcher)
// in tests/utilities/PwnedPasswordsTest.php.
App\Utilities\ValidationManager::setPwnedChecker(fn (string $password): bool => false);

// A fixed, non-secret key so CryptographyManager (used for the signup
// form's submission-timing token) works without loading a real .env file.
$_ENV['CRYPTO_KEY'] ??= base64_encode(str_repeat('t', 32));
