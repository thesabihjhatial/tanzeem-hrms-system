<?php

namespace App\Core\Console;

use App\Utilities\ScaffoldManager;

class MakeAppCommand
{
    public function run(string $name): void
    {
        $studly = ScaffoldManager::createApp(dirname(__DIR__, 2), $name);

        echo "Created apps/{$studly}\n";
        echo "Add it to config/installed_apps.php to register it:\n";
        echo "  '{$name}' => ['namespace' => 'App\\\\Apps\\\\{$studly}', 'path' => __DIR__ . '/../apps/{$studly}'],\n";
    }
}
