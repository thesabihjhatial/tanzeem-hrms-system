<?php

namespace App\Apps\Employees\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\ChartManager;
use App\Utilities\EmployeeManager;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        $customerId = AuthenticationManager::customerId();

        return $this->view('employees/index.twig', [
            'employees' => EmployeeManager::listForCustomer($customerId),
            'department_breakdown' => ChartManager::pieWithLeaders(EmployeeManager::departmentBreakdown($customerId)),
            'city_breakdown' => ChartManager::pieWithLeaders(EmployeeManager::cityBreakdown($customerId)),
        ]);
    }

    public function show(Request $request, string $id): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        $employee = EmployeeManager::find(AuthenticationManager::customerId(), (int) $id);

        if ($employee === null) {
            return $this->notFound();
        }

        return $this->view('employees/show.twig', ['employee' => $employee]);
    }
}
