<?php

use App\Utilities\EnvironmentManager;
use App\Utilities\MigrationManager;

require __DIR__ . '/vendor/autoload.php';

EnvironmentManager::load(__DIR__);

$expectedToken = $_ENV['MIGRATE_TOKEN'] ?? null;
$providedToken = $_GET['token'] ?? null;

if (!$expectedToken || !$providedToken || !hash_equals($expectedToken, $providedToken)) {

    http_response_code(403);
    echo 'Forbidden';

    exit;

}

header('Content-Type: text/plain');

try {

    echo MigrationManager::run(__DIR__);
    echo "\nDone. Delete or rename migrate.php now.\n";

} catch (\Throwable $e) {

    http_response_code(500);
    echo 'Migration failed: ' . $e->getMessage() . "\n";

}
