<?php

use App\Apps\Customers\Controllers\CustomerController;
use App\Core\Router;

return function (Router $router): void {

    $router->get('/login', CustomerController::class . '@showLogin');
    $router->post('/login', CustomerController::class . '@login');
    
    $router->get('/signup', CustomerController::class . '@showSignup');
    $router->post('/signup', CustomerController::class . '@signup');
    $router->post('/check-email', CustomerController::class . '@checkEmail');
    $router->post('/check-password', CustomerController::class . '@checkPassword');
    $router->post('/check-cnic', CustomerController::class . '@checkCnic');
    $router->get('/signup/verify', CustomerController::class . '@showVerify');
    $router->post('/signup/verify', CustomerController::class . '@verify');
    $router->get('/signup/plan', CustomerController::class . '@showPlan');
    $router->post('/signup/plan', CustomerController::class . '@choosePlan');
    
    $router->get('/logout', CustomerController::class . '@logout');
    
    };
