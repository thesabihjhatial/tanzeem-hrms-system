<?php

namespace App\Core;

use App\Utilities\DatabaseManager;
use App\Utilities\ExceptionManager;

class Bootstrap
{
    public readonly Router $router;

    /** @param array<string, mixed> $settings */
    public function __construct(private readonly array $settings)
    {
        DatabaseManager::connect($settings['database']);

        $installedApps = require __DIR__ . '/../config/installed_apps.php';

        $templatePaths = [];
        foreach ($installedApps as $name => $app) {
            $templatesDir = $app['path'] . '/templates';
            if (is_dir($templatesDir)) {
                $templatePaths[$name] = $templatesDir;
            }
        }
        View::boot(__DIR__ . '/../templates', $templatePaths);

        $this->router = new Router();
        $rootRoutes = require __DIR__ . '/../config/routes.php';
        $this->router->include($rootRoutes);
        foreach ($installedApps as $app) {
            $routesFile = $app['path'] . '/routes.php';
            if (file_exists($routesFile)) {
                $registerRoutes = require $routesFile;
                $this->router->include($registerRoutes);
            }
        }

        set_exception_handler([$this, 'handleException']);
    }

    public function handle(Request $request): Response
    {
        return $this->router->dispatch($request);
    }

    public function handleException(\Throwable $e): void
    {
        ExceptionManager::handle($e, $this->settings['app']['debug'] ?? false)->send();
    }

    /** @return array<int, array{name: string, path: string}> migration directories for all installed apps */
    public function migrationPaths(): array
    {
        $installedApps = require __DIR__ . '/../config/installed_apps.php';
        $paths = [];
        foreach ($installedApps as $name => $app) {
            $migrationsDir = $app['path'] . '/migrations';
            if (is_dir($migrationsDir)) {
                $paths[] = ['name' => $name, 'path' => $migrationsDir];
            }
        }

        return $paths;
    }
}
