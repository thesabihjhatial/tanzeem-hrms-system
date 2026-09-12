<?php

use App\Core\Bootstrap;
use App\Core\Request;
use App\Utilities\AuthenticationManager;
use App\Utilities\EnvironmentManager;

require __DIR__ . '/vendor/autoload.php';

EnvironmentManager::load(__DIR__);

AuthenticationManager::configureSession();
session_start();

$settings = require __DIR__ . '/config/settings.php';
$bootstrap = new Bootstrap($settings);
$response = $bootstrap->handle(Request::fromGlobals());
$response->send();
