<?php

namespace App\Core\Console;

use App\Utilities\MigrationManager;

class MigrateCommand
{
    public function run(): void
    {
        echo MigrationManager::run(__DIR__ . '/../..');
    }
}
