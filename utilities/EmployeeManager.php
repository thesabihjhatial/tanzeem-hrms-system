<?php

// Tanzeem HRMS System Employee Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Employees\Models\Employee;

class EmployeeManager
{

    private const EMPLOYEE_ID_PREFIX = 'EMP-';

    private const EMPLOYEE_ID_PAD_LENGTH = 4;

    /** @return array<int, Employee> */
    public static function listForCustomer(int $customerId): array
    {

        return Employee::all($customerId);

    }

    public static function find(int $customerId, int $id): ?Employee
    {

        return Employee::find($customerId, $id);

    }

    /** @param array{employee_id?: ?string, first_name: string, last_name: string, department?: ?string, designation?: ?string, email: string, phone?: ?string, city?: ?string} $data */
    public static function create(int $customerId, array $data): Employee
    {

        $employeeId = trim((string) ($data['employee_id'] ?? ''));

        if ($employeeId === '') {

            $employeeId = self::generateEmployeeId($customerId);

        }

        return Employee::create($customerId, [
            'employee_id' => $employeeId,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'department' => $data['department'] ?? null,
            'designation' => $data['designation'] ?? null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
        ]);

    }

    private static function generateEmployeeId(int $customerId): string
    {

        $next = Employee::maxNumericEmployeeId($customerId) + 1;

        return self::EMPLOYEE_ID_PREFIX . str_pad((string) $next, self::EMPLOYEE_ID_PAD_LENGTH, '0', STR_PAD_LEFT);

    }

}
