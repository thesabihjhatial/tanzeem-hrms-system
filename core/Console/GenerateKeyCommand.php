<?php

namespace App\Core\Console;

use App\Utilities\CryptographyManager;

class GenerateKeyCommand
{
    public function run(): void
    {
        echo "CRYPTO_KEY=" . CryptographyManager::generateKey() . "\n";
        echo "Paste this into your .env file.\n";
    }
}
