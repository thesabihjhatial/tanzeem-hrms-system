<?php

namespace App\Core\Console;

use App\Utilities\DesignManager;

class GenerateDesignTokensCommand
{
    public function run(): void
    {
        DesignManager::generate(__DIR__ . '/../../templates');
        echo "Wrote templates/tokens.css\n";
    }
}
