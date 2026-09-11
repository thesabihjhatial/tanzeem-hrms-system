<?php

use App\Utilities\EnvironmentManager;

require __DIR__ . '/vendor/autoload.php';

EnvironmentManager::load(__DIR__);

$installedApps = require __DIR__ . '/config/installed_apps.php';
$migrationPaths = [];

foreach ($installedApps as $app) {

    $dir = $app['path'] . '/migrations';

    if (is_dir($dir)) {

        $migrationPaths[] = $dir;

    }

}

$dbEnvironment = [

    'adapter' => $_ENV['DB_DRIVER'] ?? 'mysql',
    'host' => $_ENV['DB_HOST'] ?? '127.0.0.1',
    'name' => $_ENV['DB_DATABASE'] ?? 'tanzeem',
    'user' => $_ENV['DB_USERNAME'] ?? 'root',
    'pass' => $_ENV['DB_PASSWORD'] ?? '',
    'port' => $_ENV['DB_PORT'] ?? '3306',
    'charset' => 'utf8mb4',

];

return [

    'paths' => [

        'migrations' => $migrationPaths,

    ],
    'environments' => [

        'default_migration_table' => 'phinxlog',
        'default_environment' => $_ENV['APP_ENV'] ?? EnvironmentManager::DEFAULT_ENVIRONMENT,
        'development' => $dbEnvironment,
        'production' => $dbEnvironment,

    ],

];
