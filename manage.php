#!/usr/bin/env php
<?php

// Tanzeem HRMS System developed and maintained by Sabih

use App\Core\Console\GenerateDesignTokensCommand;
use App\Core\Console\GenerateKeyCommand;
use App\Core\Console\MakeAppCommand;
use App\Core\Console\MakeMigrationCommand;
use App\Core\Console\MigrateCommand;
use App\Utilities\EnvironmentManager;

require __DIR__ . '/vendor/autoload.php';

EnvironmentManager::load(__DIR__);

$command = $argv[1] ?? null;
$args = array_slice($argv, 2);

match ($command) {

    'serve' => passthru('php -S 127.0.0.1:8000 -t ' . __DIR__ . ' ' . __DIR__ . '/core/dev-router.php'),
    'migrate' => (new MigrateCommand())->run(),
    'key:generate' => (new GenerateKeyCommand())->run(),
    'design:tokens' => (new GenerateDesignTokensCommand())->run(),
    'make:app' => (new MakeAppCommand())->run($args[0] ?? throw new \InvalidArgumentException('Usage: manage.php make:app <name>')),
    'make:migration' => (new MakeMigrationCommand())->run(

        $args[0] ?? throw new \InvalidArgumentException('Usage: manage.php make:migration <name> --app=<app>'),
        appFromArgs($args),

    ),
    default => printUsage(),

};

function appFromArgs(array $args): string
{

    foreach ($args as $arg) {

        if (str_starts_with($arg, '--app=')) {

            return substr($arg, strlen('--app='));

        }

    }

    throw new \InvalidArgumentException('Missing --app=<app> flag');

}

function printUsage(): void
{

    echo <<<TEXT
    Tanzeem manage.php

    Usage:
      php manage.php serve                           Start dev server on http://127.0.0.1:8000
      php manage.php migrate                         Run pending migrations for all installed apps
      php manage.php key:generate                    Generate a CRYPTO_KEY for .env
      php manage.php design:tokens                   Regenerate templates/tokens.css from Design Manager
      php manage.php make:app <name>                 Scaffold new app under apps/
      php manage.php make:migration <name> --app=X   Scaffold new migration for app

    TEXT;

}
