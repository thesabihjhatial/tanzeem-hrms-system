<?php

// Tanzeem HRMS System Employee Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Employees\Models\Employee;

class EmployeeManager
{

    /** @return array<int, Employee> */
    public static function listForCustomer(int $customerId): array
    {

        return Employee::all($customerId);

    }

    public static function find(int $customerId, int $id): ?Employee
    {

        return Employee::find($customerId, $id);

    }

    /** @param array{first_name: string, last_name: string, email: string} $data */
    public static function create(int $customerId, array $data): Employee
    {

        return Employee::create($customerId, $data);

    }

}
