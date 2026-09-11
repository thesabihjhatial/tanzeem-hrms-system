<?php

use App\Apps\Customers\Controllers\CustomerController;
use App\Core\Router;

return function (Router $router): void {
    $router->get('/signup', CustomerController::class . '@showSignup');
    $router->post('/signup', CustomerController::class . '@signup');
    $router->get('/login', CustomerController::class . '@showLogin');
    $router->post('/login', CustomerController::class . '@login');
    $router->get('/logout', CustomerController::class . '@logout');
    $router->post('/check-password', CustomerController::class . '@checkPassword');
};
