<?php

use App\Apps\Dashboard\Controllers\DashboardController;
use App\Core\Router;

return function (Router $router): void {

    $router->get('/dashboard', DashboardController::class . '@index');

};
