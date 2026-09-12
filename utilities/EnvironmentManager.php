<?php

// Tanzeem HRMS System Environment Manager developed and maintained by Sabih

namespace App\Utilities;

use Dotenv\Dotenv;

class EnvironmentManager
{

    public const DEFAULT_ENVIRONMENT = 'development';

    public static function load(string $rootPath): void
    {

        Dotenv::createMutable($rootPath, '.env')->safeLoad();

        $environment = $_ENV['APP_ENV'] ?? self::DEFAULT_ENVIRONMENT;
        $envFile = ".env.{$environment}";

        if (is_file("{$rootPath}/{$envFile}")) {

            Dotenv::createMutable($rootPath, $envFile)->safeLoad();

        }

    }

    public static function isProduction(): bool
    {

        return ($_ENV['APP_ENV'] ?? self::DEFAULT_ENVIRONMENT) === 'production';

    }

}
