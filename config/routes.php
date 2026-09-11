<?php

/**
 * Root URL config. Kept for parity with Django's root urls.py; the actual
 * per-app route registration happens automatically in Bootstrap.php by
 * reading config/installed_apps.php and including each app's routes.php.
 * Add site-wide routes (health checks, etc.) here.
 */
return function (\App\Core\Router $router): void {
    $router->get('/', 'App\\Apps\\Employees\\Controllers\\EmployeeController@index');
};
