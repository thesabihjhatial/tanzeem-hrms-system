<?php

/**
 * Each entry maps a URL-safe app name to its namespace and base directory.
 * Bootstrap.php iterates this list to register each app's routes.php,
 * migrations/ path, and templates/ directory. This is Tanzeem's analog
 * of Django's INSTALLED_APPS.
 */
return [
    'customers' => [
        'namespace' => 'App\\Apps\\Customers',
        'path' => __DIR__ . '/../apps/Customers',
    ],
    'billing' => [
        'namespace' => 'App\\Apps\\Billing',
        'path' => __DIR__ . '/../apps/Billing',
    ],
    'employees' => [
        'namespace' => 'App\\Apps\\Employees',
        'path' => __DIR__ . '/../apps/Employees',
    ],
    // 'attendance' => ['namespace' => 'App\\Apps\\Attendance', 'path' => __DIR__ . '/../apps/Attendance'],
    // 'leave'      => ['namespace' => 'App\\Apps\\Leave',      'path' => __DIR__ . '/../apps/Leave'],
    // 'payroll'    => ['namespace' => 'App\\Apps\\Payroll',    'path' => __DIR__ . '/../apps/Payroll'],
    // 'auth'       => ['namespace' => 'App\\Apps\\Auth',       'path' => __DIR__ . '/../apps/Auth'],
];
