<?php

require __DIR__ . '/../vendor/autoload.php';

// The test suite must never depend on a live network call. The pwned-password
// matching logic itself is covered directly (with an injected fake fetcher)
// in tests/utilities/PwnedPasswordsTest.php.
App\Utilities\ValidationManager::setPwnedChecker(fn (string $password): bool => false);
