# Merge `users` into `employees` Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Drop the `users` table and fold login/auth fields (`password_hash`, `role`, `id_number`) directly into `employees`, so every employee is a potential login and access is governed by a two-value `role` (`admin`/`viewer`) instead of the old owner/admin split.

**Architecture:** `Employee` absorbs everything `User` had. `AuthenticationManager`, `core/Controller.php`, `CustomerManager`, and `DashboardManager` swap their `User` dependency for `Employee`. Every signup (individual or company) unconditionally creates exactly one `admin` employee via `EmployeeManager::createAdminEmployee()` — the asymmetry where company signups skipped employee creation is removed. The dev DB is being recreated from scratch, so migrations are consolidated (original schema migrations rewritten to final shape, redundant follow-ups deleted) rather than kept as an additive trail.

**Tech Stack:** Vanilla PHP 8.1+, Phinx migrations, PHPUnit 10 + SQLite in-memory (tests), MySQL (dev), Twig templates.

**Spec:** `docs/superpowers/specs/2026-09-12-merge-users-into-employees-design.md`

## Global Constraints

- Centralize logic in Managers — Controllers and Models stay thin (`utilities/*Manager.php` owns all business logic).
- Every Manager class's constants must be sorted alphabetically by name.
- `email` and `id_number` on `employees` are globally unique (not scoped per customer) — login must resolve unambiguously.
- `password_hash` is optional per employee; only the signup-bootstrap admin is guaranteed one.
- No invite/set-password-by-email flow — out of scope for this plan.
- No permission-gating logic (what a `viewer` can/can't do) — this plan only introduces the `role` column and its values.

---

### Task 1: Rewrite the Employees app's schema migration and delete its now-redundant follow-ups

**Files:**
- Modify: `apps/Employees/migrations/20260912000002_create_employees_table.php`
- Delete: `apps/Employees/migrations/20260912000012_add_employee_details.php`
- Delete: `apps/Employees/migrations/20260912000015_link_employees_to_users.php`

**Interfaces:**
- Produces: the final `employees` table shape that every later task's model/manager code assumes: `id, customer_id, employee_id, first_name, last_name, department, designation, email (unique), phone, city, password_hash, role (default 'viewer'), id_number (unique), created_at, updated_at`. Unique index `(customer_id, employee_id)` still applies (per-tenant custom labels); `email` and `id_number` are unique globally (no `customer_id` in those indexes).

- [ ] **Step 1: Rewrite `create_employees_table` to its final shape**

Replace the full contents of `apps/Employees/migrations/20260912000002_create_employees_table.php` with:

```php
<?php

use Phinx\Migration\AbstractMigration;

class CreateEmployeesTable extends AbstractMigration
{
    public function change(): void
    {
        $table = $this->table('employees');
        $table
            ->addColumn('customer_id', 'integer', ['signed' => false])
            ->addColumn('employee_id', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('first_name', 'string', ['limit' => 100])
            ->addColumn('last_name', 'string', ['limit' => 100])
            ->addColumn('department', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('designation', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('phone', 'string', ['limit' => 20, 'null' => true])
            ->addColumn('city', 'string', ['limit' => 100, 'null' => true])
            ->addColumn('password_hash', 'string', ['limit' => 255, 'null' => true])
            ->addColumn('role', 'string', ['limit' => 20, 'default' => 'viewer'])
            ->addColumn('id_number', 'string', ['limit' => 50, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['customer_id', 'employee_id'], ['unique' => true])
            ->addIndex(['email'], ['unique' => true])
            ->addIndex(['id_number'], ['unique' => true])
            ->addForeignKey('customer_id', 'customers', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
```

- [ ] **Step 2: Delete the now-redundant follow-up migrations**

```bash
rm apps/Employees/migrations/20260912000012_add_employee_details.php
rm apps/Employees/migrations/20260912000015_link_employees_to_users.php
```

- [ ] **Step 3: Commit**

```bash
git add apps/Employees/migrations/20260912000002_create_employees_table.php
git rm apps/Employees/migrations/20260912000012_add_employee_details.php
git rm apps/Employees/migrations/20260912000015_link_employees_to_users.php
git commit -m "Consolidate employees table migration into final shape (adds auth columns, drops users FK)"
```

---

### Task 2: Rewrite the Customers app's schema migration, split out auth_identities, and delete redundant follow-ups

**Files:**
- Modify: `apps/Customers/migrations/20260912000001_create_customers_schema.php`
- Create: `apps/Customers/migrations/20260912000003_create_auth_identities.php`
- Delete: `apps/Customers/migrations/20260912000006_add_cnic_to_users.php`
- Delete: `apps/Customers/migrations/20260912000011_add_customer_type_and_id_number.php`

**Interfaces:**
- Consumes: the `employees` table from Task 1 (this task's new `auth_identities` migration has a timestamp — `20260912000003` — after Employees' `20260912000002`, so `employees` exists by the time its foreign key is created).
- Produces: `customers` (with `customer_type` folded in directly), `signup_otps`, `login_throttles`, `logs` tables from the rewritten schema migration; `auth_identities` (pointing at `employees.id`) from the new migration.

- [ ] **Step 1: Rewrite `create_customers_schema` — drop `users` and `auth_identities`, fold in `customer_type`**

Replace the full contents of `apps/Customers/migrations/20260912000001_create_customers_schema.php` with:

```php
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Consolidated baseline for the Customers app's schema. Login/auth now
 * lives on employees (see apps/Employees/migrations) — there is no
 * separate users table. auth_identities is created in its own later
 * migration (create_auth_identities) since it needs the employees
 * table to exist first for its foreign key.
 */
class CreateCustomersSchema extends AbstractMigration
{
    public function change(): void
    {
        $customers = $this->table('customers');
        $customers
            ->addColumn('company_name', 'string', ['limit' => 150])
            ->addColumn('customer_type', 'string', ['limit' => 20, 'default' => 'individual'])
            ->addColumn('phone', 'string', ['limit' => 20])
            ->addColumn('province', 'string', ['limit' => 50])
            ->addColumn('city', 'string', ['limit' => 100])
            ->addColumn('employee_count_range', 'string', ['limit' => 20])
            // Compliance fields, filled in during a post-signup setup wizard —
            // most small owners won't have these memorized at signup time.
            ->addColumn('ntn', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('eobi_registration_no', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('pessi_registration_no', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('business_type', 'string', ['limit' => 30, 'null' => true])
            ->addColumn('created_at', 'datetime')
            ->create();

        $signupOtps = $this->table('signup_otps');
        $signupOtps
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('otp_hash', 'string', ['limit' => 255])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('payload', 'text')
            ->addColumn('expires_at', 'datetime')
            ->addColumn('created_at', 'datetime')
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $loginThrottles = $this->table('login_throttles');
        $loginThrottles
            ->addColumn('email', 'string', ['limit' => 150])
            ->addColumn('attempts', 'integer', ['default' => 0])
            ->addColumn('locked_until', 'datetime', ['null' => true])
            ->addColumn('updated_at', 'datetime', ['null' => true])
            ->addIndex(['email'], ['unique' => true])
            ->create();

        $logs = $this->table('logs');
        $logs
            ->addColumn('level', 'string', ['limit' => 20])
            ->addColumn('source', 'string', ['limit' => 100])
            ->addColumn('message', 'text')
            ->addColumn('context', 'text', ['null' => true])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['level'])
            ->addIndex(['created_at'])
            ->create();
    }
}
```

- [ ] **Step 2: Create the `auth_identities` migration (runs after employees exists)**

Create `apps/Customers/migrations/20260912000003_create_auth_identities.php`:

```php
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Not used by any flow yet — a landing place for OAuth provider links
 * (Google, Microsoft, etc.) once that work starts, so it doesn't need
 * its own schema migration then. Points at employees.id now that
 * login identity lives there instead of a separate users table.
 */
class CreateAuthIdentities extends AbstractMigration
{
    public function change(): void
    {
        $authIdentities = $this->table('auth_identities');
        $authIdentities
            ->addColumn('employee_id', 'integer', ['signed' => false])
            ->addColumn('provider', 'string', ['limit' => 50])
            ->addColumn('provider_user_id', 'string', ['limit' => 255])
            ->addColumn('created_at', 'datetime')
            ->addIndex(['provider', 'provider_user_id'], ['unique' => true])
            ->addForeignKey('employee_id', 'employees', 'id', ['delete' => 'CASCADE'])
            ->create();
    }
}
```

- [ ] **Step 3: Delete the now-redundant follow-up migrations**

```bash
rm apps/Customers/migrations/20260912000006_add_cnic_to_users.php
rm apps/Customers/migrations/20260912000011_add_customer_type_and_id_number.php
```

- [ ] **Step 4: Commit**

```bash
git add apps/Customers/migrations/20260912000001_create_customers_schema.php apps/Customers/migrations/20260912000003_create_auth_identities.php
git rm apps/Customers/migrations/20260912000006_add_cnic_to_users.php
git rm apps/Customers/migrations/20260912000011_add_customer_type_and_id_number.php
git commit -m "Consolidate customers schema migration, drop users table, repoint auth_identities at employees"
```

---

### Task 3: Rewrite the seed migrations (merge Test Company's user+employee into one)

**Files:**
- Modify: `apps/Customers/migrations/20260912000005_seed_test_customer.php`

**Interfaces:**
- Consumes: `employees` table shape from Task 1.
- Produces: seeded row `admin@tanzeem.pk` / `pak@123` on `employees` directly (role `admin`, `employee_id = '1'`), so manual/dev login keeps working after a fresh `phinx migrate`.

- [ ] **Step 1: Rewrite `seed_test_customer` to create the employee directly instead of a user**

Replace the full contents of `apps/Customers/migrations/20260912000005_seed_test_customer.php` with:

```php
<?php

use Phinx\Migration\AbstractMigration;

/**
 * Dev convenience login: admin@tanzeem.pk / pak@123. Fixture data
 * inserted directly via Phinx, not through CustomerManager/EmployeeManager.
 * Don't run this against a real production database.
 */
class SeedTestCustomer extends AbstractMigration
{
    public function up(): void
    {
        $now = date('Y-m-d H:i:s');

        $this->table('customers')->insert([
            'company_name' => 'Test Company',
            'phone' => '03001234567',
            'province' => 'punjab',
            'city' => 'Lahore',
            'employee_count_range' => '1-10',
            'created_at' => $now,
        ])->saveData();

        $customerId = (int) $this->fetchRow("SELECT id FROM customers WHERE company_name = 'Test Company' ORDER BY id DESC LIMIT 1")['id'];

        $this->table('employees')->insert([
            'customer_id' => $customerId,
            'employee_id' => '1',
            'first_name' => 'Test',
            'last_name' => 'User',
            'email' => 'admin@tanzeem.pk',
            'phone' => '03001234567',
            'password_hash' => password_hash('pak@123', PASSWORD_DEFAULT),
            'role' => 'admin',
            'created_at' => $now,
        ])->saveData();

        $planId = (int) $this->fetchRow('SELECT id FROM plans WHERE is_default = 1 LIMIT 1')['id'];

        $this->table('subscriptions')->insert([
            'customer_id' => $customerId,
            'plan_id' => $planId,
            'status' => 'trial',
            'trial_ends_at' => date('Y-m-d H:i:s', strtotime('+7 days')),
            'created_at' => $now,
        ])->saveData();
    }

    public function down(): void
    {
        // FK cascades (customers -> employees, customers -> subscriptions) clean up the rest.
        $this->execute("DELETE FROM customers WHERE company_name = 'Test Company'");
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/Customers/migrations/20260912000005_seed_test_customer.php
git commit -m "Seed the test admin directly as an employee instead of a separate user"
```

---

### Task 4: Rewrite the `Employee` model to its final shape

**Files:**
- Modify: `apps/Employees/Models/Employee.php`
- Test: `tests/employees/EmployeeModelTest.php`

**Interfaces:**
- Consumes: `employees` table shape from Task 1.
- Produces: `Employee` constructor `(id, customer_id, employee_id, first_name, last_name, department, designation, email, phone, city, password_hash, role, id_number)`; static methods `all(customerId)`, `find(customerId, id)`, `findByEmail(email)` (global), `findByIdNumber(idNumber)` (global), `maxNumericEmployeeId(customerId)`, `create(customerId, data)`. These exact names/shapes are what `EmployeeManager` (Task 5) and `AuthenticationManager` (Task 6) call.

- [ ] **Step 1: Update the inline SQLite schema in `EmployeeModelTest.php` to the final shape**

Replace the `CREATE TABLE employees` block in `tests/employees/EmployeeModelTest.php`'s `setUp()` with:

```php
        $pdo->exec(<<<SQL
            CREATE TABLE employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                employee_id TEXT,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                department TEXT,
                designation TEXT,
                email TEXT NOT NULL,
                phone TEXT,
                city TEXT,
                password_hash TEXT,
                role TEXT NOT NULL DEFAULT 'viewer',
                id_number TEXT,
                created_at TEXT,
                UNIQUE (email)
            )
            SQL);
```

- [ ] **Step 2: Add a failing test for the new `findByEmail`/`findByIdNumber` (global) behavior**

Add to `tests/employees/EmployeeModelTest.php`:

```php
    public function test_find_by_email_is_global_not_scoped_to_a_customer(): void
    {
        Employee::create(1, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@tanzeem.test']);

        $employee = Employee::findByEmail('ada@tanzeem.test');

        $this->assertNotNull($employee);
        $this->assertSame(1, $employee->customer_id);
    }

    public function test_find_by_id_number_returns_null_when_not_set(): void
    {
        Employee::create(1, ['first_name' => 'Ada', 'last_name' => 'Lovelace', 'email' => 'ada@tanzeem.test']);

        $this->assertNull(Employee::findByIdNumber('3520112345671'));
    }

    public function test_find_by_id_number_finds_a_match(): void
    {
        Employee::create(1, [
            'first_name' => 'Ada',
            'last_name' => 'Lovelace',
            'email' => 'ada@tanzeem.test',
            'id_number' => '3520112345671',
        ]);

        $employee = Employee::findByIdNumber('3520112345671');

        $this->assertNotNull($employee);
        $this->assertSame('ada@tanzeem.test', $employee->email);
    }
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/employees/EmployeeModelTest.php`
Expected: FAIL — `Call to undefined method App\Apps\Employees\Models\Employee::findByEmail()` (or similar for `findByIdNumber`).

- [ ] **Step 4: Rewrite `Employee.php` to its final shape**

Replace the full contents of `apps/Employees/Models/Employee.php` with:

```php
<?php

namespace App\Apps\Employees\Models;

use App\Utilities\DatabaseManager;

class Employee
{
    public function __construct(
        public readonly int $id,
        public readonly int $customer_id,
        public readonly ?string $employee_id,
        public readonly string $first_name,
        public readonly string $last_name,
        public readonly ?string $department,
        public readonly ?string $designation,
        public readonly string $email,
        public readonly ?string $phone,
        public readonly ?string $city,
        public readonly ?string $password_hash,
        public readonly string $role,
        public readonly ?string $id_number,
    ) {
    }

    /** @return array<int, self> */
    public static function all(int $customerId): array
    {
        $rows = DatabaseManager::select('SELECT * FROM employees WHERE customer_id = ? ORDER BY id', [$customerId]);

        return array_map(self::fromRow(...), $rows);
    }

    public static function find(int $customerId, int $id): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE customer_id = ? AND id = ?', [$customerId, $id]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByEmail(string $email): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE email = ?', [$email]);

        return $row ? self::fromRow($row) : null;
    }

    public static function findByIdNumber(string $idNumber): ?self
    {
        $row = DatabaseManager::selectOne('SELECT * FROM employees WHERE id_number = ?', [$idNumber]);

        return $row ? self::fromRow($row) : null;
    }

    public static function maxNumericEmployeeId(int $customerId): int
    {
        $rows = DatabaseManager::select('SELECT employee_id FROM employees WHERE customer_id = ? AND employee_id IS NOT NULL', [$customerId]);

        $max = 0;

        foreach ($rows as $row) {
            if (preg_match('/(\d+)$/', (string) $row['employee_id'], $matches)) {
                $max = max($max, (int) $matches[1]);
            }
        }

        return $max;
    }

    /** @param array{employee_id?: ?string, first_name: string, last_name: string, department?: ?string, designation?: ?string, email: string, phone?: ?string, city?: ?string, password_hash?: ?string, role?: string, id_number?: ?string} $data */
    public static function create(int $customerId, array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO employees (customer_id, employee_id, first_name, last_name, department, designation, email, phone, city, password_hash, role, id_number, created_at) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)',
            [
                $customerId,
                $data['employee_id'] ?? null,
                $data['first_name'],
                $data['last_name'],
                $data['department'] ?? null,
                $data['designation'] ?? null,
                $data['email'],
                $data['phone'] ?? null,
                $data['city'] ?? null,
                $data['password_hash'] ?? null,
                $data['role'] ?? 'viewer',
                $data['id_number'] ?? null,
                date('Y-m-d H:i:s'),
            ],
        );

        return self::find($customerId, (int) DatabaseManager::lastInsertId());
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self(
            (int) $row['id'],
            (int) $row['customer_id'],
            $row['employee_id'],
            $row['first_name'],
            $row['last_name'],
            $row['department'],
            $row['designation'],
            $row['email'],
            $row['phone'],
            $row['city'],
            $row['password_hash'],
            $row['role'],
            $row['id_number'],
        );
    }
}
```

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/employees/EmployeeModelTest.php`
Expected: PASS (all tests, including the pre-existing ones — `Employee::create()` still works with only `first_name`/`last_name`/`email` supplied, since every other field defaults via `?? null`/`?? 'viewer'`).

- [ ] **Step 6: Commit**

```bash
git add apps/Employees/Models/Employee.php tests/employees/EmployeeModelTest.php
git commit -m "Absorb password_hash/role/id_number into the Employee model"
```

---

### Task 5: Update `AuthIdentity` model to reference `employee_id`

**Files:**
- Modify: `apps/Customers/Models/AuthIdentity.php`

**Interfaces:**
- Consumes: `auth_identities.employee_id` column from Task 2.
- Produces: `AuthIdentity` constructor now exposes `employee_id` instead of `user_id`. Nothing in the codebase calls this class yet (confirmed dormant), so no other file changes as a result of this rename.

- [ ] **Step 1: Rewrite `AuthIdentity.php`**

Replace the full contents of `apps/Customers/Models/AuthIdentity.php` with:

```php
<?php

namespace App\Apps\Customers\Models;

use App\Utilities\DatabaseManager;

/**
 * Not used by any flow yet — a landing place for OAuth provider links
 * (Google, Microsoft, etc.) once that work starts, so it doesn't need
 * its own schema migration then.
 */
class AuthIdentity
{
    public function __construct(
        public readonly int $id,
        public readonly int $employee_id,
        public readonly string $provider,
        public readonly string $provider_user_id,
    ) {
    }

    public static function findByProvider(string $provider, string $providerUserId): ?self
    {
        $row = DatabaseManager::selectOne(
            'SELECT * FROM auth_identities WHERE provider = ? AND provider_user_id = ?',
            [$provider, $providerUserId],
        );

        return $row ? self::fromRow($row) : null;
    }

    /** @param array{employee_id: int, provider: string, provider_user_id: string} $data */
    public static function create(array $data): self
    {
        DatabaseManager::execute(
            'INSERT INTO auth_identities (employee_id, provider, provider_user_id, created_at) VALUES (?, ?, ?, ?)',
            [$data['employee_id'], $data['provider'], $data['provider_user_id'], date('Y-m-d H:i:s')],
        );

        return self::findByProvider($data['provider'], $data['provider_user_id']);
    }

    /** @param array<string, mixed> $row */
    private static function fromRow(array $row): self
    {
        return new self((int) $row['id'], (int) $row['employee_id'], $row['provider'], $row['provider_user_id']);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add apps/Customers/Models/AuthIdentity.php
git commit -m "Rename AuthIdentity's user_id to employee_id"
```

---

### Task 6: Rewrite `EmployeeManager` — roles, global lookups, `createAdminEmployee`

**Files:**
- Modify: `utilities/EmployeeManager.php`

**Interfaces:**
- Consumes: `Employee` model from Task 4 (`findByEmail(email)`, `findByIdNumber(idNumber)` — both global now).
- Produces: `EmployeeManager::ROLE_ADMIN` / `ROLE_VIEWER` constants; `EmployeeManager::findByEmail(string $email): ?Employee` (global, no `$customerId`); `EmployeeManager::findByIdNumber(string $idNumber): ?Employee` (new); `EmployeeManager::createAdminEmployee(int $customerId, array $data): Employee` (renamed from `createOwnerEmployee`, now also accepts `id_number`/`password_hash`, department/designation left `null`); `EmployeeManager::create()` now also passes through `password_hash`/`role`/`id_number` (role defaults to `ROLE_VIEWER` when not given). `findByUserId()` is removed. These are the exact names Task 7 (`CustomerManager`) and Task 8 (`AuthenticationManager`) call.

- [ ] **Step 1: Rewrite `EmployeeManager.php`**

Replace the full contents of `utilities/EmployeeManager.php` with:

```php
<?php

// Tanzeem HRMS System Employee Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Employees\Models\Employee;

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

    public static function find(int $customerId, int $id): ?Employee
    {

        return Employee::find($customerId, $id);

    }

    public static function findByEmail(string $email): ?Employee
    {

        return Employee::findByEmail($email);

    }

    public static function findByIdNumber(string $idNumber): ?Employee
    {

        return Employee::findByIdNumber($idNumber);

    }

    /** @param array{employee_id?: ?string, first_name: string, last_name: string, department?: ?string, designation?: ?string, email: string, phone?: ?string, city?: ?string, password_hash?: ?string, role?: string, id_number?: ?string} $data */
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
            'password_hash' => $data['password_hash'] ?? null,
            'role' => $data['role'] ?? self::ROLE_VIEWER,
            'id_number' => $data['id_number'] ?? null,
        ]);

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

        return self::breakdownBy($customerId, fn (Employee $employee) => $employee->department);

    }

    /** @return array<int, array{label: string, count: int, percent: float}> */
    public static function cityBreakdown(int $customerId): array
    {

        return self::breakdownBy($customerId, fn (Employee $employee) => $employee->city);

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
```

- [ ] **Step 2: Commit**

```bash
git add utilities/EmployeeManager.php
git commit -m "EmployeeManager: admin/viewer roles, global email/id_number lookups, createAdminEmployee"
```

---

### Task 7: Swap `AuthenticationManager` from `User` to `Employee`, delete the `User` model

**Files:**
- Modify: `utilities/AuthenticationManager.php`
- Delete: `apps/Customers/Models/User.php`
- Test: `tests/utilities/AuthenticationManagerTest.php`

**Interfaces:**
- Consumes: `Employee::findByEmail()`, `Employee::find()` from Task 4; `EmployeeManager` role constants aren't needed here (auth doesn't check role).
- Produces: `AuthenticationManager::attempt()`, `login()`, `check()`, `guard()`, `customerId()`, `userId()`, `currentEmployee()` (renamed from `currentUser()`) — same signatures except `User` type-hints become `Employee`. `SESSION_USER_ID`/`userId()` naming is kept as-is (it stores the employee's `id`, no session-key rename).

- [ ] **Step 1: Update `AuthenticationManagerTest.php` for the rename**

Replace the full contents of `tests/utilities/AuthenticationManagerTest.php` with:

```php
<?php

namespace App\Tests\Utilities;

use App\Tests\Support\BuildsCustomerRegistrationData;
use App\Tests\Support\SeedsBillingSchema;
use App\Utilities\AuthenticationManager;
use App\Utilities\DatabaseManager;
use PHPUnit\Framework\TestCase;

class AuthenticationManagerTest extends TestCase
{
    use SeedsBillingSchema;
    use BuildsCustomerRegistrationData;

    protected function setUp(): void
    {
        DatabaseManager::reset();
        $pdo = DatabaseManager::connect(['driver' => 'sqlite', 'database' => ':memory:']);
        $this->seedBillingSchema($pdo);

        $_SESSION = [];
        $this->registerCustomer();
        $_SESSION = [];
    }

    public function test_attempt_returns_the_employee_on_correct_credentials(): void
    {
        $employee = AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple');

        $this->assertNotNull($employee);
        $this->assertSame('ada@acme.test', $employee->email);
    }

    public function test_attempt_returns_null_on_wrong_password(): void
    {
        $this->assertNull(AuthenticationManager::attempt('ada@acme.test', 'wrong password'));
    }

    public function test_attempt_returns_null_for_unknown_email(): void
    {
        $this->assertNull(AuthenticationManager::attempt('nobody@acme.test', 'anything'));
    }

    public function test_login_populates_the_session(): void
    {
        $employee = AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple');
        AuthenticationManager::login($employee);

        $this->assertTrue(AuthenticationManager::check());
        $this->assertSame($employee->id, AuthenticationManager::userId());
        $this->assertSame($employee->customer_id, AuthenticationManager::customerId());
    }

    public function test_attempt_login_is_true_and_logs_in_on_success(): void
    {
        $this->assertTrue(AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple'));
        $this->assertTrue(AuthenticationManager::check());
    }

    public function test_attempt_login_is_false_and_does_not_log_in_on_failure(): void
    {
        $this->assertFalse(AuthenticationManager::attemptLogin('ada@acme.test', 'wrong password'));
        $this->assertFalse(AuthenticationManager::check());
    }

    public function test_guard_returns_null_when_authenticated(): void
    {
        AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple');

        $this->assertNull(AuthenticationManager::guard());
    }

    public function test_guard_returns_a_redirect_when_not_authenticated(): void
    {
        $response = AuthenticationManager::guard();

        $this->assertNotNull($response);
        $this->assertSame(302, $response->status);
        $this->assertSame('/login', $response->headers['Location']);
    }

    public function test_attempt_locks_out_after_five_wrong_passwords(): void
    {
        for ($i = 0; $i < 5; $i++) {
            AuthenticationManager::attempt('ada@acme.test', 'wrong password');
        }

        // Even the real password no longer works once locked out.
        $this->assertNull(AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple'));
    }

    public function test_attempt_lockout_applies_equally_to_unknown_emails(): void
    {
        // A lockout must never behave differently for an email that has
        // no account — otherwise the lockout itself becomes a way to
        // enumerate which emails are registered.
        for ($i = 0; $i < 5; $i++) {
            AuthenticationManager::attempt('nobody@acme.test', 'wrong password');
        }

        $row = DatabaseManager::selectOne('SELECT locked_until FROM login_throttles WHERE email = ?', ['nobody@acme.test']);

        $this->assertNotNull($row['locked_until']);
    }

    public function test_check_expires_the_session_after_the_absolute_timeout(): void
    {
        AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple');
        $this->assertTrue(AuthenticationManager::check());

        $_SESSION['authenticated_at'] = time() - AuthenticationManager::SESSION_TIMEOUT_SECONDS - 1;

        $this->assertFalse(AuthenticationManager::check());
    }

    public function test_check_logs_out_a_session_whose_employee_no_longer_exists(): void
    {
        $employee = AuthenticationManager::attempt('ada@acme.test', 'correct horse battery staple');
        AuthenticationManager::login($employee);

        DatabaseManager::execute('DELETE FROM employees WHERE id = ?', [$employee->id]);

        $this->assertFalse(AuthenticationManager::check());
    }

    public function test_current_employee_returns_the_logged_in_employee(): void
    {
        AuthenticationManager::attemptLogin('ada@acme.test', 'correct horse battery staple');

        $employee = AuthenticationManager::currentEmployee();

        $this->assertNotNull($employee);
        $this->assertSame('ada@acme.test', $employee->email);
    }

    public function test_current_employee_returns_null_when_not_authenticated(): void
    {
        $this->assertNull(AuthenticationManager::currentEmployee());
    }
}
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/utilities/AuthenticationManagerTest.php`
Expected: FAIL — `Call to undefined method App\Utilities\AuthenticationManager::currentEmployee()` (and other `User`-vs-`Employee` mismatches once `AuthenticationManager` still returns `User` objects).

- [ ] **Step 3: Rewrite `AuthenticationManager.php`**

Replace the full contents of `utilities/AuthenticationManager.php` with:

```php
<?php

// Tanzeem HRMS System Authentication Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Customers\Models\LoginThrottle;
use App\Apps\Employees\Models\Employee;
use App\Core\Response;

class AuthenticationManager
{

    private const LOGIN_LOCKOUT_SECONDS = 180;

    private const MAX_LOGIN_ATTEMPTS = 5;

    private const SESSION_AUTHENTICATED_AT = 'authenticated_at';

    private const SESSION_CUSTOMER_ID = 'customer_id';

    public const SESSION_TIMEOUT_SECONDS = 10800;

    private const SESSION_USER_ID = 'user_id';

    public static function configureSession(): void
    {

        ini_set('session.use_strict_mode', '1');

        session_set_cookie_params([
            'lifetime' => 0,
            'path' => '/',
            'domain' => '',
            'secure' => EnvironmentManager::isProduction(),
            'httponly' => true,
            'samesite' => 'Lax',
        ]);

    }

    public static function attemptLogin(string $email, string $password): bool
    {

        $employee = self::attempt($email, $password);

        if ($employee === null) {

            return false;

        }

        self::login($employee);

        return true;

    }

    public static function attempt(string $email, string $password): ?Employee
    {

        $employee = Employee::findByEmail($email);

        $passwordCorrect = password_verify($password, $employee->password_hash ?? self::dummyHash());

        $throttle = LoginThrottle::findByEmail($email);

        if ($throttle !== null && $throttle->isLocked()) {

            return null;

        }

        if ($employee === null || $employee->password_hash === null || !$passwordCorrect) {

            LoginThrottle::recordFailure($email, self::MAX_LOGIN_ATTEMPTS, self::LOGIN_LOCKOUT_SECONDS);

            return null;

        }

        LoginThrottle::clear($email);

        return $employee;

    }

    public static function login(Employee $employee): void
    {

        if (session_status() === PHP_SESSION_ACTIVE) {

            session_regenerate_id(true);

        }

        $_SESSION[self::SESSION_USER_ID] = $employee->id;
        $_SESSION[self::SESSION_CUSTOMER_ID] = $employee->customer_id;
        $_SESSION[self::SESSION_AUTHENTICATED_AT] = time();

    }

    public static function logout(): void
    {

        $_SESSION = [];

        if (session_status() === PHP_SESSION_ACTIVE) {

            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            session_destroy();

        }

    }

    public static function check(): bool
    {

        if (!isset($_SESSION[self::SESSION_CUSTOMER_ID], $_SESSION[self::SESSION_USER_ID])) {

            return false;

        }

        $authenticatedAt = $_SESSION[self::SESSION_AUTHENTICATED_AT] ?? 0;

        if (time() - $authenticatedAt > self::SESSION_TIMEOUT_SECONDS) {

            self::logout();

            return false;

        }

        if (Employee::find((int) $_SESSION[self::SESSION_CUSTOMER_ID], (int) $_SESSION[self::SESSION_USER_ID]) === null) {

            self::logout();

            return false;

        }

        return true;

    }

    public static function guard(): ?Response
    {

        if (self::check()) {

            return null;

        }

        return new Response('', 302, ['Location' => '/login']);

    }

    public static function customerId(): ?int
    {

        return self::check() ? (int) $_SESSION[self::SESSION_CUSTOMER_ID] : null;

    }

    public static function userId(): ?int
    {

        return self::check() ? (int) $_SESSION[self::SESSION_USER_ID] : null;

    }

    public static function currentEmployee(): ?Employee
    {

        $userId = self::userId();
        $customerId = self::customerId();

        return ($userId !== null && $customerId !== null) ? Employee::find($customerId, $userId) : null;

    }

    private static function dummyHash(): string
    {

        static $hash = null;

        return $hash ??= password_hash('tanzeem-timing-safety-placeholder', PASSWORD_ARGON2ID);

    }

}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/utilities/AuthenticationManagerTest.php`
Expected: PASS (all tests) — this run will still fail at this point because `CustomerManager` (Task 8) hasn't been updated yet and `BuildsCustomerRegistrationData::registerCustomer()` calls into it. Skip ahead to Task 8 before re-running if this step fails with unrelated `User` errors, then return here to confirm green.

- [ ] **Step 5: Delete the `User` model**

```bash
rm apps/Customers/Models/User.php
```

- [ ] **Step 6: Commit**

```bash
git add utilities/AuthenticationManager.php tests/utilities/AuthenticationManagerTest.php
git rm apps/Customers/Models/User.php
git commit -m "AuthenticationManager operates on Employee; delete the User model"
```

---

### Task 8: Rewrite `CustomerManager` — unconditional admin employee creation, renamed lookups, dropped role constants

**Files:**
- Modify: `utilities/CustomerManager.php`
- Test: `tests/customers/CustomerManagerTest.php`

**Interfaces:**
- Consumes: `EmployeeManager::createAdminEmployee()`, `EmployeeManager::findByEmail()`, `EmployeeManager::findByIdNumber()` from Task 6; `AuthenticationManager::login(Employee $employee)` from Task 7.
- Produces: `CustomerManager::findEmployeeByEmail(string $email): ?Employee` (renamed from `findUserByEmail`), `CustomerManager::findEmployeeByIdNumber(string $idNumber): ?Employee` (renamed from `findUserByIdNumber`), `CustomerManager::finalizeRegistration()` returns `['success', 'customer', 'employee', 'subscription']` (was `'user'`). `ROLE_ADMIN`/`ROLE_OWNER` constants are removed. Task 9 (`CustomerController`) calls the renamed lookup methods.

- [ ] **Step 1: Rewrite `CustomerManagerTest.php`'s role/user assertions**

In `tests/customers/CustomerManagerTest.php`, replace the `test_register_creates_a_customer_and_its_first_user` and `test_register_gives_company_customers_the_owner_role` tests with:

```php
    public function test_register_creates_a_customer_and_its_first_employee(): void
    {
        $result = $this->registerCustomer();

        $this->assertTrue($result['success']);
        $this->assertSame('Acme Inc', $result['customer']->company_name);
        $this->assertSame('03001234567', $result['customer']->phone);
        $this->assertSame('punjab', $result['customer']->province);
        $this->assertSame($result['customer']->id, $result['employee']->customer_id);
        $this->assertSame('03001234567', $result['employee']->phone);
        $this->assertSame('admin', $result['employee']->role);
        $this->assertSame('1', $result['employee']->employee_id);
        $this->assertTrue(password_verify('correct horse battery staple', $result['employee']->password_hash));
    }

    public function test_register_gives_company_customers_the_admin_role_too(): void
    {
        $result = $this->registerCustomer(['customer_type' => 'company', 'cnic' => 'NTN123X']);

        $this->assertTrue($result['success']);
        $this->assertSame('admin', $result['employee']->role);
    }
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/phpunit tests/customers/CustomerManagerTest.php`
Expected: FAIL — `Undefined array key "employee"` (the manager still returns `'user'`).

- [ ] **Step 3: Rewrite `CustomerManager.php`**

Replace the full contents of `utilities/CustomerManager.php` with:

```php
<?php

// Tanzeem HRMS System Customer Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Customers\Models\Customer;
use App\Apps\Customers\Models\SignupOtp;
use App\Apps\Employees\Models\Employee;

class CustomerManager
{

    private const DEV_OTP_BYPASS = '123456';

    public const EMPLOYEE_COUNT_RANGES = [
        '1-10' => '1-10 employees',
        '11-25' => '11-25 employees',
        '26-50' => '26-50 employees',
    ];

    private const ERROR_CNIC_TAKEN = 'This CNIC is already registered.';

    private const ERROR_EMAIL_TAKEN = 'This email is already registered.';

    private const ERROR_NTN_TAKEN = 'This NTN is already registered.';

    private const ERROR_OTP_EXPIRED = 'Your verification session has expired. Please start over.';

    private const ERROR_OTP_INCORRECT = 'Incorrect verification code.';

    private const ERROR_OTP_LOCKED = 'Too many incorrect verification attempts. Please start over.';

    private const ERROR_PASSWORD_MISMATCH = 'The passwords do not match.';

    private const ERROR_REGISTRATION_FAILED = 'Account creation failed. The incident has been logged for investigation.';

    private const LOG_SOURCE = 'CustomerManager';

    private const MAX_OTP_ATTEMPTS = 3;

    private const MIN_SUBMISSION_SECONDS = 3;

    private const OTP_LENGTH = 6;

    private const OTP_TTL_SECONDS = 900;

    public const PROVINCES = [
        'ajk' => 'Azad Jammu & Kashmir',
        'balochistan' => 'Balochistan',
        'gb' => 'Gilgit-Baltistan',
        'ict' => 'Islamabad Capital Territory',
        'kpk' => 'Khyber Pakhtunkhwa',
        'punjab' => 'Punjab',
        'sindh' => 'Sindh',
    ];

    private const SESSION_PENDING_EMAIL_KEY = 'pending_signup_email';

    private const SESSION_VERIFIED_KEY = 'pending_signup_verified';

    /** @param array<string, mixed> $data @return array{success: bool, errors?: array<string, string>, otp?: string} */
    public static function startRegistration(array $data): array
    {

        if (($data['tzm_hp'] ?? '') !== '') {

            LogManager::warning(self::LOG_SOURCE, 'Whoa there, genius.');

            return ['success' => false, 'errors' => ['email' => self::ERROR_REGISTRATION_FAILED]];

        }

        if (!self::wasSubmittedByAHuman($data['form_token'] ?? '')) {

            LogManager::warning(self::LOG_SOURCE, 'Whoa, slow down.');

            return ['success' => false, 'errors' => ['email' => self::ERROR_REGISTRATION_FAILED]];

        }

        $errors = ValidationManager::validate($data, self::registrationRules($data));

        if (!isset($errors['confirm_password']) && ($data['password'] ?? '') !== ($data['confirm_password'] ?? '')) {

            $errors['confirm_password'] = self::ERROR_PASSWORD_MISMATCH;

        }

        $passwordCheck = ['errors' => [], 'warnings' => []];

        if (!isset($errors['password'])) {

            $passwordCheck = ValidationManager::checkPasswordStrength($data['password'] ?? '', $data['email'] ?? '');

            if ($passwordCheck['errors'] !== []) {

                $errors['password'] = implode(' ', $passwordCheck['errors']);

            }

        }

        if ($errors !== []) {

            return ['success' => false, 'errors' => $errors];

        }

        if (self::findEmployeeByEmail($data['email']) !== null) {

            return ['success' => false, 'errors' => ['email' => self::ERROR_EMAIL_TAKEN]];

        }

        $customerType = ($data['customer_type'] ?? 'individual') === 'company' ? 'company' : 'individual';

        if (self::findEmployeeByIdNumber($data['cnic']) !== null) {

            return ['success' => false, 'errors' => ['cnic' => $customerType === 'company' ? self::ERROR_NTN_TAKEN : self::ERROR_CNIC_TAKEN]];

        }

        $otp = self::generateOtp();

        $payload = [
            'customer' => [
                'company_name' => $data['company_name'],
                'customer_type' => $customerType,
                'phone' => $data['phone'],
                'province' => $data['province'],
                'city' => $data['city'],
                'employee_count_range' => $data['employee_count_range'],
            ],
            'employee' => [
                'name' => $data['name'],
                'email' => $data['email'],
                'phone' => $data['phone'],
                'id_number' => $data['cnic'],
                'password_hash' => password_hash($data['password'], PASSWORD_ARGON2ID),
            ],
        ];

        SignupOtp::create([
            'email' => $data['email'],
            'otp_hash' => password_hash($otp, PASSWORD_ARGON2ID),
            'payload' => json_encode($payload),
            'expires_at' => date('Y-m-d H:i:s', time() + self::OTP_TTL_SECONDS),
        ]);

        unset($_SESSION[self::SESSION_VERIFIED_KEY]);
        $_SESSION[self::SESSION_PENDING_EMAIL_KEY] = $data['email'];

        return ['success' => true, 'otp' => $otp];

    }

    /** @return array{success: bool, error?: string} */
    public static function confirmRegistration(string $otp): array
    {

        $pendingOtp = self::currentPendingOtp();

        if ($pendingOtp === null) {

            return ['success' => false, 'error' => self::ERROR_OTP_EXPIRED];

        }

        if ($pendingOtp->attempts >= self::MAX_OTP_ATTEMPTS) {

            SignupOtp::deleteByEmail($pendingOtp->email);
            unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

            return ['success' => false, 'error' => self::ERROR_OTP_LOCKED];

        }

        $isDevBypass = $otp === self::DEV_OTP_BYPASS && !EnvironmentManager::isProduction();

        if (!$isDevBypass && !password_verify($otp, $pendingOtp->otp_hash)) {

            SignupOtp::incrementAttempts($pendingOtp->email);

            if ($pendingOtp->attempts + 1 >= self::MAX_OTP_ATTEMPTS) {

                SignupOtp::deleteByEmail($pendingOtp->email);
                unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

                return ['success' => false, 'error' => self::ERROR_OTP_LOCKED];

            }

            return ['success' => false, 'error' => self::ERROR_OTP_INCORRECT];

        }

        $_SESSION[self::SESSION_VERIFIED_KEY] = true;

        return ['success' => true];

    }

    /** @return array{success: bool, error?: string, customer?: Customer, employee?: Employee, subscription?: \App\Apps\Billing\Models\Subscription} */
    public static function finalizeRegistration(int $planId): array
    {

        $pendingOtp = self::currentVerifiedOtp();

        if ($pendingOtp === null) {

            return ['success' => false, 'error' => self::ERROR_OTP_EXPIRED];

        }

        if (Plan::find($planId) === null) {

            return ['success' => false, 'error' => 'Please choose a plan first.'];

        }

        $payload = json_decode($pendingOtp->payload, true);

        DatabaseManager::beginTransaction();

        try {

            $customer = Customer::create($payload['customer']);

            $employee = EmployeeManager::createAdminEmployee($customer->id, [
                'name' => $payload['employee']['name'],
                'email' => $payload['employee']['email'],
                'phone' => $payload['employee']['phone'],
                'city' => $payload['customer']['city'],
                'id_number' => $payload['employee']['id_number'],
                'password_hash' => $payload['employee']['password_hash'],
            ]);

            $subscription = SubscriptionManager::startTrial($customer->id, $planId);

            DatabaseManager::commit();

        } catch (\Throwable $e) {

            DatabaseManager::rollBack();
            LogManager::error(self::LOG_SOURCE, 'Registration failed: ' . $e->getMessage());

            return ['success' => false, 'error' => self::ERROR_REGISTRATION_FAILED];

        }

        SignupOtp::deleteByEmail($pendingOtp->email);
        unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);
        AuthenticationManager::login($employee);

        return ['success' => true, 'customer' => $customer, 'employee' => $employee, 'subscription' => $subscription];

    }

    public static function hasPendingRegistration(): bool
    {

        return self::currentPendingOtp() !== null;

    }

    public static function hasVerifiedPendingRegistration(): bool
    {

        return self::currentVerifiedOtp() !== null;

    }

    /** @return array<string, mixed>|null */
    public static function pendingSignupCustomerData(): ?array
    {

        $pendingOtp = self::currentVerifiedOtp();

        if ($pendingOtp === null) {

            return null;

        }

        $payload = json_decode($pendingOtp->payload, true);

        return $payload['customer'] ?? null;

    }

    public static function findEmployeeByEmail(string $email): ?Employee
    {

        return EmployeeManager::findByEmail($email);

    }

    public static function findEmployeeByIdNumber(string $idNumber): ?Employee
    {

        return EmployeeManager::findByIdNumber($idNumber);

    }

    public static function generateFormToken(): string
    {

        return CryptographyManager::encrypt((string) time());

    }

    private static function generateOtp(): string
    {

        return str_pad((string) random_int(0, 10 ** self::OTP_LENGTH - 1), self::OTP_LENGTH, '0', STR_PAD_LEFT);

    }

    /** @param array<string, mixed> $data @return array<string, array<int, string|array{0: string, 1: mixed}>> */
    private static function registrationRules(array $data): array
    {

        $isCompany = ($data['customer_type'] ?? 'individual') === 'company';

        $nameRules = $isCompany
            ? ['required']
            : ['required', 'min:5', 'safe_text', 'has_letter', 'not_monotonous'];

        $cnicRules = $isCompany ? ['required', 'ntn_pk'] : ['required', 'cnic_pk'];

        return [
            'name' => $nameRules,
            'company_name' => ['required', 'min:3', 'safe_text', 'has_letter', 'not_monotonous'],
            'phone' => ['required', 'phone_pk'],
            'cnic' => $cnicRules,
            'email' => ['required', 'email', 'not_disposable_email'],
            'password' => ['required', 'min:8'],
            'confirm_password' => ['required'],
            'province' => ['required', ['in', array_keys(self::PROVINCES)]],
            'city' => ['required', 'min:2', 'safe_text', 'has_letter', 'not_monotonous'],
            'employee_count_range' => ['required', ['in', array_keys(self::EMPLOYEE_COUNT_RANGES)]],
        ];

    }

    private static function currentPendingOtp(): ?SignupOtp
    {

        $email = $_SESSION[self::SESSION_PENDING_EMAIL_KEY] ?? null;

        if ($email === null) {

            return null;

        }

        $pendingOtp = SignupOtp::findByEmail($email);

        if ($pendingOtp === null || $pendingOtp->isExpired()) {

            unset($_SESSION[self::SESSION_PENDING_EMAIL_KEY], $_SESSION[self::SESSION_VERIFIED_KEY]);

            if ($pendingOtp !== null) {

                SignupOtp::deleteByEmail($email);

            }

            return null;

        }

        return $pendingOtp;

    }

    private static function currentVerifiedOtp(): ?SignupOtp
    {

        if (!($_SESSION[self::SESSION_VERIFIED_KEY] ?? false)) {

            return null;

        }

        return self::currentPendingOtp();

    }

    private static function wasSubmittedByAHuman(string $formToken): bool
    {

        try {

            $renderedAt = (int) CryptographyManager::decrypt($formToken);

        } catch (\Throwable) {

            return false;

        }

        return (time() - $renderedAt) >= self::MIN_SUBMISSION_SECONDS;

    }

}
```

- [ ] **Step 4: Run the tests to verify they pass**

Run: `vendor/bin/phpunit tests/customers/CustomerManagerTest.php`
Expected: PASS (all tests).

- [ ] **Step 5: Commit**

```bash
git add utilities/CustomerManager.php tests/customers/CustomerManagerTest.php
git commit -m "CustomerManager creates one admin employee per signup unconditionally; drop owner/admin role split"
```

---

### Task 9: Update `CustomerController`'s renamed method calls

**Files:**
- Modify: `apps/Customers/Controllers/CustomerController.php:157,169`

**Interfaces:**
- Consumes: `CustomerManager::findEmployeeByEmail()` / `findEmployeeByIdNumber()` from Task 8.

- [ ] **Step 1: Update `checkEmail()` and `checkCnic()`**

In `apps/Customers/Controllers/CustomerController.php`, change:

```php
    public function checkEmail(Request $request): Response
    {
        $taken = CustomerManager::findUserByEmail($request->body['email'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }
```

to:

```php
    public function checkEmail(Request $request): Response
    {
        $taken = CustomerManager::findEmployeeByEmail($request->body['email'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }
```

and change:

```php
    public function checkCnic(Request $request): Response
    {
        $taken = CustomerManager::findUserByIdNumber($request->body['cnic'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }
```

to:

```php
    public function checkCnic(Request $request): Response
    {
        $taken = CustomerManager::findEmployeeByIdNumber($request->body['cnic'] ?? '') !== null;

        return $this->json(['taken' => $taken]);
    }
```

- [ ] **Step 2: Commit**

```bash
git add apps/Customers/Controllers/CustomerController.php
git commit -m "CustomerController: use renamed findEmployeeByEmail/findEmployeeByIdNumber"
```

---

### Task 10: Simplify `core/Controller.php` — one `current_employee` lookup, no more `current_user`

**Files:**
- Modify: `core/Controller.php`

**Interfaces:**
- Consumes: `AuthenticationManager::currentEmployee()` from Task 7.
- Produces: every view now receives `current_employee` (an `?Employee`) instead of `current_user`. Task 11 (`header.twig`) is the only template consumer.

- [ ] **Step 1: Rewrite `Controller.php`**

Replace the full contents of `core/Controller.php` with:

```php
<?php

namespace App\Core;

use App\Utilities\AuthenticationManager;
use App\Utilities\FlashManager;

abstract class Controller
{
    /** @param array<string, mixed> $data */
    protected function view(string $template, array $data = []): Response
    {
        $data['flash_messages'] ??= FlashManager::consume();
        $data['current_employee'] ??= AuthenticationManager::currentEmployee();

        return Response::html(View::render($template, $data));
    }

    /** @param array<string, mixed> $data */
    protected function json(array $data, int $status = 200): Response
    {
        return Response::json($data, $status);
    }

    protected function notFound(): Response
    {
        return Response::html('Not Found', 404);
    }

    protected function redirect(string $location): Response
    {
        return new Response('', 302, ['Location' => $location]);
    }
}
```

- [ ] **Step 2: Commit**

```bash
git add core/Controller.php
git commit -m "Controller: inject current_employee directly, drop current_user"
```

---

### Task 11: Update `header.twig` to drop the `current_user` fallback

**Files:**
- Modify: `templates/components/header.twig`

**Interfaces:**
- Consumes: `current_employee` from Task 10.

- [ ] **Step 1: Update the profile block**

In `templates/components/header.twig`, change:

```twig
        {% if current_user %}
            <div class="profile" id="profile">
                <button type="button" class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
                    <span class="profile-info">
                        <span class="profile-name">{{ current_employee ? current_employee.first_name ~ ' ' ~ current_employee.last_name : current_user.name }}</span>
                        <span class="profile-role">{{ current_employee.employee_id ?? '' }}</span>
                    </span>
                    {{ avatar.avatar(current_user.photo_url ?? null, 'profile-avatar') }}
                    <svg class="profile-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
```

to:

```twig
        {% if current_employee %}
            <div class="profile" id="profile">
                <button type="button" class="profile-trigger" id="profileTrigger" aria-haspopup="true" aria-expanded="false">
                    <span class="profile-info">
                        <span class="profile-name">{{ current_employee.first_name ~ ' ' ~ current_employee.last_name }}</span>
                        <span class="profile-role">{{ current_employee.employee_id ?? '' }}</span>
                    </span>
                    {{ avatar.avatar(current_employee.photo_url ?? null, 'profile-avatar') }}
                    <svg class="profile-caret" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="6 9 12 15 18 9"/></svg>
                </button>
```

- [ ] **Step 2: Commit**

```bash
git add templates/components/header.twig
git commit -m "header.twig: read current_employee directly, drop current_user fallback"
```

---

### Task 12: Simplify `DashboardManager` — one `Employee` lookup, drop the fallback branch

**Files:**
- Modify: `utilities/DashboardManager.php`

**Interfaces:**
- Consumes: `Employee::find(customerId, userId)` (`DashboardManager` fetches the employee directly since it already has both IDs from the controller).
- Produces: `DashboardManager::overviewForCustomer()`'s `you` key is always a populated array when an employee exists (no more `User`/`Customer` fallback shape). `apps/Dashboard/Controllers/DashboardController.php` calls `overviewForCustomer($customerId, $userId)` via `AuthenticationManager::customerId()`/`userId()` — both unchanged, so that controller needs no edits.

- [ ] **Step 1: Rewrite `DashboardManager.php`**

Replace the full contents of `utilities/DashboardManager.php` with:

```php
<?php

// Tanzeem HRMS System Dashboard Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Apps\Billing\Models\Plan;
use App\Apps\Employees\Models\Employee;

class DashboardManager
{

    /** @return array<string, mixed> */
    public static function overviewForCustomer(int $customerId, int $userId): array
    {

        $employee = Employee::find($customerId, $userId);
        $subscription = SubscriptionManager::forCustomer($customerId);
        $plan = $subscription !== null ? Plan::find($subscription->plan_id) : null;

        return [
            'greeting' => self::greeting(),
            'user_name' => $employee !== null ? trim($employee->first_name . ' ' . $employee->last_name) : null,
            'you' => $employee !== null ? self::youProfile($employee) : null,
            'employee_count' => count(EmployeeManager::listForCustomer($customerId)),
            'employee_limit' => $plan?->employee_limit,
            'is_trial' => $subscription?->status === 'trial',
            'plan_name' => $plan?->name,
            'plan_billing_cycle' => $plan?->billing_cycle,
            'subscription_status' => $subscription?->status,
            'subscription_status_variant' => $subscription !== null ? SubscriptionManager::statusVariant($subscription->status) : null,
            'next_renewal_date' => $subscription?->current_period_end ?? $subscription?->trial_ends_at,
            'plan_price' => $plan?->price,
        ];

    }

    /** @return array{employee_id: ?string, name: string, department: ?string, designation: ?string, email: string, phone: ?string, city: ?string} */
    private static function youProfile(Employee $employee): array
    {

        return [
            'employee_id' => $employee->employee_id,
            'name' => trim($employee->first_name . ' ' . $employee->last_name),
            'department' => $employee->department,
            'designation' => $employee->designation,
            'email' => $employee->email,
            'phone' => $employee->phone,
            'city' => $employee->city,
        ];

    }

    private static function greeting(): string
    {

        $hour = (int) date('G');

        return match (true) {

            $hour < 12 => 'Good morning',
            $hour < 17 => 'Good afternoon',
            default => 'Good evening',

        };

    }

}
```

- [ ] **Step 2: Commit**

```bash
git add utilities/DashboardManager.php
git commit -m "DashboardManager: one Employee lookup, drop User/Customer fallback branch"
```

---

### Task 13: Update the shared SQLite test fixture

**Files:**
- Modify: `tests/Support/SeedsBillingSchema.php`

**Interfaces:**
- Consumes: nothing (this is the schema every other test file's `setUp()` builds on).
- Produces: the SQLite schema every existing `Customers`/`Dashboard`/`Employees` test relies on, matching the final MySQL shape from Tasks 1–2.

- [ ] **Step 1: Update `seedBillingSchema()`**

In `tests/Support/SeedsBillingSchema.php`, remove the `CREATE TABLE users` block entirely:

```php
        $pdo->exec(<<<SQL
            CREATE TABLE users (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                name TEXT NOT NULL,
                email TEXT NOT NULL UNIQUE,
                phone TEXT,
                id_number TEXT,
                password_hash TEXT,
                role TEXT NOT NULL,
                created_at TEXT
            )
            SQL);

```

Change the `auth_identities` table's `user_id` to `employee_id`:

```php
        $pdo->exec(<<<SQL
            CREATE TABLE auth_identities (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL,
                provider TEXT NOT NULL,
                provider_user_id TEXT NOT NULL,
                created_at TEXT,
                UNIQUE (provider, provider_user_id)
            )
            SQL);
```

Change the `employees` table to the final shape (drop `user_id`, add `password_hash`/`role`/`id_number`, unique on `email` alone):

```php
        $pdo->exec(<<<SQL
            CREATE TABLE employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                employee_id TEXT,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                department TEXT,
                designation TEXT,
                email TEXT NOT NULL UNIQUE,
                phone TEXT,
                city TEXT,
                password_hash TEXT,
                role TEXT NOT NULL DEFAULT 'viewer',
                id_number TEXT,
                created_at TEXT
            )
            SQL);
```

- [ ] **Step 2: Run the full suite to verify everything passes together**

Run: `vendor/bin/phpunit`
Expected: PASS — all tests (this is the first point every test file's schema and every production class are simultaneously consistent).

- [ ] **Step 3: Commit**

```bash
git add tests/Support/SeedsBillingSchema.php
git commit -m "Update shared SQLite test fixture: drop users table, employees gains auth columns"
```

---

### Task 14: Fresh migration + manual smoke test + regenerate `sql/schema.sql`

**Files:**
- None modified directly — this task exercises the real MySQL dev database and regenerates the schema dump.

**Interfaces:**
- Consumes: every migration from Tasks 1–3 in final form.

- [ ] **Step 1: Drop and recreate the dev database, then migrate from scratch**

```bash
/c/xampp/mysql/bin/mysql.exe -u root -e "DROP DATABASE IF EXISTS tanzeem; CREATE DATABASE tanzeem;"
vendor/bin/phinx migrate
```

Expected: all migrations run cleanly in order (`create_customers_schema` → `create_employees_table` → `create_auth_identities` → `seed_test_customer` → `seed_test_employees`), no foreign key errors.

- [ ] **Step 2: Confirm the seeded admin employee row looks right**

```bash
/c/xampp/mysql/bin/mysql.exe -u root tanzeem -e "SELECT id, employee_id, email, role, password_hash IS NOT NULL AS has_password FROM employees WHERE email = 'admin@tanzeem.pk';"
```

Expected: one row, `employee_id = '1'`, `role = 'admin'`, `has_password = 1`.

- [ ] **Step 3: Log in as the seeded admin through the running dev server**

With the PHP dev server running (`php -S 127.0.0.1:8000` from the project root, or however it's normally started), log in with `admin@tanzeem.pk` / `pak@123` and confirm:
- Login succeeds and redirects to `/dashboard`.
- The header shows "Test User" and employee ID `1`.
- The "You" card on the dashboard shows the same data (no "Coming soon" fallback).

- [ ] **Step 4: Run through a fresh signup (both individual and company) to confirm the new unconditional employee creation**

Sign up as a new individual account and a new company account (through the browser or via `curl` with the CSRF/form tokens, matching the pattern used earlier in this project's manual testing). For both, confirm via SQL that exactly one `employees` row was created with `role = 'admin'` and `employee_id = '1'`:

```bash
/c/xampp/mysql/bin/mysql.exe -u root tanzeem -e "SELECT customer_id, employee_id, role, email FROM employees WHERE email IN ('<individual-test-email>', '<company-test-email>');"
```

- [ ] **Step 5: Regenerate `sql/schema.sql`**

```bash
head -9 sql/schema.sql > /tmp/schema_header.sql
/c/xampp/mysql/bin/mysqldump.exe --no-data --skip-comments --routines=false --triggers=false --ignore-table=tanzeem.phinxlog -u root tanzeem >> /tmp/schema_header.sql
mv /tmp/schema_header.sql sql/schema.sql
grep -c "CREATE TABLE \`users\`" sql/schema.sql
```

Expected: the `grep -c` returns `0` (no `users` table in the dump).

- [ ] **Step 6: Run the full PHPUnit suite one final time**

Run: `vendor/bin/phpunit`
Expected: PASS (all tests).

- [ ] **Step 7: Commit the regenerated schema dump**

```bash
git add sql/schema.sql
git commit -m "Regenerate sql/schema.sql after the users/employees merge"
```

---

## Self-Review Notes

- **Spec coverage:** every section of the spec (schema, model/manager, auth, signup-flow, template, testing) has a corresponding task above. The "Confirmed decision" about `finalizeRegistration()`'s `employee` key is implemented in Task 8.
- **Type consistency:** `EmployeeManager::createAdminEmployee()` (Task 6) matches the exact call signature used in `CustomerManager::finalizeRegistration()` (Task 8) — `name`, `email`, `phone`, `city`, `id_number`, `password_hash`. `Employee::find(customerId, id)` is used consistently by `AuthenticationManager::check()`/`currentEmployee()` (Task 7) and `DashboardManager::overviewForCustomer()` (Task 12) with the same argument order.
- **Manager constants alphabetized:** `EmployeeManager`'s constants (Task 6) are `EMPLOYEE_ID_PAD_LENGTH, EMPLOYEE_ID_PREFIX, OWNER_EMPLOYEE_ID, ROLE_ADMIN, ROLE_VIEWER` — alphabetical. `CustomerManager`'s constants (Task 8) keep their existing alphabetical order with `ROLE_ADMIN`/`ROLE_OWNER` removed.
- **`apps/Dashboard/Controllers/DashboardController.php`** was checked during planning — it calls `DashboardManager::overviewForCustomer(AuthenticationManager::customerId(), AuthenticationManager::userId())`, both of which keep their existing names and behavior after this merge. No change needed there.

---

## Execution Handoff

Plan complete and saved to `docs/superpowers/plans/2026-09-12-merge-users-into-employees.md`. Two execution options:

**1. Subagent-Driven (recommended)** - I dispatch a fresh subagent per task, review between tasks, fast iteration

**2. Inline Execution** - Execute tasks in this session using executing-plans, batch execution with checkpoints

**Which approach?**
