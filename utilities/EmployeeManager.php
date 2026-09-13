<?php

// Tanzeem HRMS System Employee Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Employees\Models\Employee;
use App\Apps\Employees\Models\EmployeeInfo;

class EmployeeManager
{

    private const EMPLOYEE_ID_PAD_LENGTH = 4;

    private const EMPLOYEE_ID_PREFIX = 'EMP-';

    private const OWNER_EMPLOYEE_ID = '1';

    public const ROLE_ADMIN = 'admin';

    public const ROLE_VIEWER = 'viewer';

    /** @return array<int, Employee> */
    public static function listForCustomer(int $customerId): array
    {

        return Employee::all($customerId);

    }

    /** @return array<int, array<string, mixed>> */
    public static function listWithInfoForCustomer(int $customerId): array
    {

        $infoByEmployeeId = EmployeeInfo::allForCustomer($customerId);

        return array_map(
            fn (Employee $employee) => self::toProfile($employee, $infoByEmployeeId[$employee->id] ?? null),
            self::listForCustomer($customerId),
        );

    }

    /** @return array<string, mixed>|null */
    public static function profileForCustomer(int $customerId, string $uuid): ?array
    {

        $employee = self::findByUuid($customerId, $uuid);

        if ($employee === null) {

            return null;

        }

        return self::toProfile($employee, EmployeeInfo::findByEmployeeId($employee->id));

    }

    /** @return array<string, mixed>|null */
    public static function currentEmployeeProfile(): ?array
    {

        $employee = AuthenticationManager::currentEmployee();

        if ($employee === null) {

            return null;

        }

        return self::toProfile($employee, EmployeeInfo::findByEmployeeId($employee->id));

    }

    public static function find(int $customerId, int $id): ?Employee
    {

        return Employee::find($customerId, $id);

    }

    public static function findByUuid(int $customerId, string $uuid): ?Employee
    {

        return Employee::findByUuid($customerId, $uuid);

    }

    public static function findByEmail(string $email): ?Employee
    {

        return Employee::findByEmail($email);

    }

    public static function findByIdNumber(string $idNumber): ?EmployeeInfo
    {

        return EmployeeInfo::findByIdNumber($idNumber);

    }

    /** @param array{employee_id?: ?string, first_name: string, last_name: string, department?: ?string, designation?: ?string, email: string, phone?: ?string, city?: ?string, password_hash?: ?string, role?: string, id_number?: ?string} $data */
    public static function create(int $customerId, array $data): Employee
    {

        $employeeId = trim((string) ($data['employee_id'] ?? ''));

        if ($employeeId === '') {

            $employeeId = self::generateEmployeeId($customerId);

        }

        $employee = Employee::create($customerId, [
            'employee_id' => $employeeId,
            'email' => $data['email'],
            'password_hash' => $data['password_hash'] ?? null,
            'role' => $data['role'] ?? self::ROLE_VIEWER,
        ]);

        EmployeeInfo::create([
            'employee_id' => $employee->id,
            'first_name' => $data['first_name'],
            'last_name' => $data['last_name'],
            'department' => $data['department'] ?? null,
            'designation' => $data['designation'] ?? null,
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'id_number' => $data['id_number'] ?? null,
        ]);

        return $employee;

    }

    /**
     * Registers a brand-new customer's own account as their first
     * employee, so every signup — individual or company — starts off
     * at 1 employee: themselves, with admin access. This is always the
     * signup bootstrap, so it's the one place password_hash is expected
     * (a plain future employee-creation form may leave it blank).
     *
     * @param array{name: string, email: string, phone?: ?string, city?: ?string, id_number?: ?string, password_hash?: ?string} $data
     */
    public static function createAdminEmployee(int $customerId, array $data): Employee
    {

        [$firstName, $lastName] = self::splitName($data['name']);

        return self::create($customerId, [
            'employee_id' => self::OWNER_EMPLOYEE_ID,
            'first_name' => $firstName,
            'last_name' => $lastName,
            'department' => null,
            'designation' => null,
            'email' => $data['email'],
            'phone' => $data['phone'] ?? null,
            'city' => $data['city'] ?? null,
            'password_hash' => $data['password_hash'] ?? null,
            'role' => self::ROLE_ADMIN,
            'id_number' => $data['id_number'] ?? null,
        ]);

    }

    /** @return array<int, array{label: string, count: int, percent: float}> */
    public static function departmentBreakdown(int $customerId): array
    {

        $infoByEmployeeId = EmployeeInfo::allForCustomer($customerId);

        return self::breakdownBy($customerId, fn (Employee $employee) => ($infoByEmployeeId[$employee->id] ?? null)?->department);

    }

    /** @return array<int, array{label: string, count: int, percent: float}> */
    public static function cityBreakdown(int $customerId): array
    {

        $infoByEmployeeId = EmployeeInfo::allForCustomer($customerId);

        return self::breakdownBy($customerId, fn (Employee $employee) => ($infoByEmployeeId[$employee->id] ?? null)?->city);

    }

    public static function hiresThisMonth(int $customerId): int
    {

        $thisMonth = date('Y-m');

        return count(array_filter(
            self::listForCustomer($customerId),
            fn (Employee $employee) => str_starts_with($employee->created_at, $thisMonth),
        ));

    }

    /** @return array<string, mixed> */
    private static function toProfile(Employee $employee, ?EmployeeInfo $info): array
    {

        return [
            'id' => $employee->id,
            'uuid' => $employee->uuid,
            'employee_id' => $employee->employee_id,
            'role' => $employee->role,
            'first_name' => $info?->first_name,
            'last_name' => $info?->last_name,
            'email' => $employee->email,
            'department' => $info?->department,
            'designation' => $info?->designation,
            'phone' => $info?->phone,
            'city' => $info?->city,
            'id_number' => $info?->id_number,
        ];

    }

    /** @return array{0: string, 1: string} */
    private static function splitName(string $name): array
    {

        $parts = preg_split('/\s+/', trim($name), 2);

        return [$parts[0] ?? '', $parts[1] ?? ''];

    }

    private static function generateEmployeeId(int $customerId): string
    {

        $next = Employee::maxNumericEmployeeId($customerId) + 1;

        return self::EMPLOYEE_ID_PREFIX . str_pad((string) $next, self::EMPLOYEE_ID_PAD_LENGTH, '0', STR_PAD_LEFT);

    }

    /** @param callable(Employee): ?string $extractor @return array<int, array{label: string, count: int, percent: float}> */
    private static function breakdownBy(int $customerId, callable $extractor): array
    {

        $employees = self::listForCustomer($customerId);
        $total = count($employees);

        if ($total === 0) {

            return [];

        }

        $counts = [];

        foreach ($employees as $employee) {

            $label = $extractor($employee) ?: 'Unassigned';
            $counts[$label] = ($counts[$label] ?? 0) + 1;

        }

        uksort($counts, fn (string $a, string $b) => $counts[$b] <=> $counts[$a] ?: strnatcasecmp($a, $b));

        $segments = [];

        foreach ($counts as $label => $count) {

            $segments[] = ['label' => $label, 'count' => $count, 'percent' => $count / $total * 100];

        }

        return $segments;

    }

}
