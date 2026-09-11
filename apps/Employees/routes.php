<?php

use App\Apps\Employees\Controllers\EmployeeController;
use App\Core\Router;

return function (Router $router): void {
    $router->get('/employees', EmployeeController::class . '@index');
    $router->get('/employees/{id}', EmployeeController::class . '@show');
};
