<?php

namespace App\Apps\Employees\Controllers;

use App\Core\Controller;
use App\Core\Request;
use App\Core\Response;
use App\Utilities\AuthenticationManager;
use App\Utilities\EmployeeManager;

class EmployeeController extends Controller
{
    public function index(Request $request): Response
    {
        if ($redirect = AuthenticationManager::guard()) {
            return $redirect;
        }

        return $this->view('employees/index.twig', [
            'employees' => EmployeeManager::listForCustomer(AuthenticationManager::customerId()),
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
