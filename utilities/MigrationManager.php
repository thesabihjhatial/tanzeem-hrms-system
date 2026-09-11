<?php

// Tanzeem HRMS System Migration Manager developed and maintained by Sabih

namespace App\Utilities;

use Phinx\Config\Config;
use Phinx\Migration\Manager;
use Symfony\Component\Console\Input\StringInput;
use Symfony\Component\Console\Output\BufferedOutput;

class MigrationManager
{

    public static function run(string $projectRoot): string
    {

        $configArray = require $projectRoot . '/phinx.php';
        $config = new Config($configArray);
        $output = new BufferedOutput();
        $manager = new Manager($config, new StringInput(''), $output);
        $manager->migrate($config->getDefaultEnvironment());

        return $output->fetch();

    }

}
