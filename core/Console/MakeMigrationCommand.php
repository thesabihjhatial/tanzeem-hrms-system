<?php

namespace App\Core\Console;

use App\Utilities\ScaffoldManager;

class MakeMigrationCommand
{
    public function run(string $name, string $app): void
    {
        $path = ScaffoldManager::createMigration(dirname(__DIR__, 2), $name, $app);

        echo "Created {$path}\n";
    }
}
