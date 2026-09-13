# Merge `users` into `employees`

## Context

Today, authentication and HR data live in two separate tables:

- `users` — login identity: `customer_id`, `name`, `email`, `id_number`, `password_hash`, `role` (`owner`/`admin`), `phone`.
- `employees` — HR record: `customer_id`, `user_id` (nullable FK to `users`), `employee_id`, `first_name`, `last_name`, `department`, `designation`, `email`, `phone`, `city`.

A `users` row doesn't necessarily have a matching `employees` row (company signups don't get one today), and vice versa (most employees have no login).

Going forward: every employee is a potential login. Access is controlled by `role`, which only takes two values — `admin` and `viewer`. The distinction between an individual owner and a company owner disappears; both are simply `admin`. This spec merges the two tables into one (`employees`), removing `users` entirely.

## Goals

- One table, one model, one identity: an employee row *is* the login credential.
- Every new signup (individual or company) creates exactly one `admin` employee — no more asymmetry where company signups skip employee creation.
- `role` becomes a real two-value enum (`admin`, `viewer`) used for future permission gating — no more `owner` vs `admin` split.
- Password is optional per employee (an admin creating a new employee later can choose whether to set one) — but the bootstrap employee created at signup always gets one (it's their own account).
- `id_number` (CNIC/NTN) and `email` stay globally unique across all customers, matching today's `users` behavior — login must resolve unambiguously.
- Consolidate migration history: since the dev DB is being recreated and nothing is deployed yet, rewrite the original schema migrations to their final shape instead of layering more incremental ones on top.

## Non-goals

- No invite/set-password-via-email flow for employees added after signup. An admin sets a new employee's password directly when creating them (or leaves it blank for a no-login HR record). Building an email-invite flow is a separate future spec.
- No actual permission-gating logic (what a `viewer` can/can't do) — this spec only introduces the `role` column and values. Enforcing it per-action is future work.
- No employee-creation UI is being built here (none exists yet); this spec only prepares the data model and the (unconditional) bootstrap-employee creation at signup.

## Schema changes

**`employees` table (rewritten `create_employees_table` migration, final shape):**

```
id                 int, PK, auto-increment
customer_id        int, unsigned, FK -> customers.id, ON DELETE CASCADE
employee_id        string(50), nullable   -- per-tenant custom label, unique per (customer_id, employee_id)
first_name         string(100)
last_name          string(100)
department         string(100), nullable
designation        string(100), nullable
email              string(150), UNIQUE (global)
phone              string(20), nullable
city               string(100), nullable
password_hash      string, nullable
role               string(20), default 'viewer'
id_number          string(50), nullable, UNIQUE (global) -- allows multiple NULLs
created_at         datetime
updated_at         datetime, nullable
```

Indexes: unique `(customer_id, employee_id)` (as today), unique `email`, unique `id_number` (MySQL unique indexes allow multiple NULLs, so employees without an `id_number` don't collide).

**`customers` table**: unchanged except it keeps `customer_type` (already added, folds into the rewritten schema migration directly instead of a follow-up).

**`auth_identities` table**: dormant OAuth-identity-link table. Its `user_id` column is renamed to `employee_id` and its FK repointed to `employees.id`. No behavior change (nothing uses this table yet).

**`users` table**: dropped entirely.

**Migration file plan:**
- Customers app: rewrite `create_customers_schema` to include `customers` (with `customer_type`), `auth_identities` (pointing to `employees`), `signup_otps`, `login_throttles`. Delete `add_cnic_to_users` and the `users`-related migration content that's now obsolete.
- Employees app: rewrite `create_employees_table` to the final shape above. Delete `add_employee_details` and `link_employees_to_users`.
- Seeds: merge `seed_test_customer` + `seed_test_owner_employee` into one seed (Test Company + its one admin employee, with a real password so login keeps working) in the Customers app. Keep `seed_test_employees` (3 extra demo employees, no login) roughly as-is in the Employees app.
- Billing app migrations are untouched.

## Model & Manager changes

**`Employee` model** (`apps/Employees/Models/Employee.php`) gains:
- Properties: `password_hash`, `role`, `id_number`.
- `findByEmail(string $email): ?self` (global, no `$customerId` param — email is globally unique now).
- `findByIdNumber(string $idNumber): ?self` (global).
- `create()` accepts `password_hash`, `role`, `id_number` in its data array.
- `user_id` property and `findByUserId()` are removed (no longer meaningful — the employee IS the identity).

**`User` model** (`apps/Customers/Models/User.php`) is deleted.

**`EmployeeManager`**:
- New constants: `ROLE_ADMIN = 'admin'`, `ROLE_VIEWER = 'viewer'` (alphabetized among existing constants).
- `findByEmail(string $email): ?Employee` and `findByIdNumber(string $idNumber): ?Employee` added (delegate to the model; both are now the global-lookup shape, no `$customerId`).
- `findByUserId()` is removed.
- `createOwnerEmployee()` is renamed `createAdminEmployee()` — same shape (name/email/phone/city + a required `password_hash` since this is always the signup bootstrap), but drops the "Administration"/"Administrator" placeholder department/designation (left `null` per your decision) and is now called unconditionally for every signup, not just individual ones.

**`CustomerManager`**:
- `ROLE_OWNER` and `ROLE_ADMIN` constants are removed entirely (role now lives on `EmployeeManager`, and there's no owner/admin split by customer type).
- `findUserByEmail()` → renamed `findEmployeeByEmail()`, delegates to `EmployeeManager::findByEmail()`.
- `findUserByIdNumber()` → renamed `findEmployeeByIdNumber()`, delegates to `EmployeeManager::findByIdNumber()`.
- `startRegistration()`'s OTP payload changes shape: the `user` sub-array becomes `employee` (same fields it already collects — name, email, phone, id_number, password_hash — just relocated/renamed in the payload).
- `finalizeRegistration()`: creates the `Customer`, then unconditionally calls `EmployeeManager::createAdminEmployee($customer->id, [...])` — no more `customer_type` branch. The employee gets `employee_id = '1'`, `role = EmployeeManager::ROLE_ADMIN`, and logs straight in via the (renamed) `AuthenticationManager::login($employee)`.

**`AuthenticationManager`**:
- Swaps its `User` import/type-hints for `Employee` throughout: `attempt()`, `login()`, `check()`, `customerId()`, `userId()` (kept as-is — session key name, still stores the employee's `id`), and `currentUser()` renamed `currentEmployee()`.
- No logic changes — same session keys, same timing-safe dummy-hash comparison, same `LoginThrottle` lockout behavior.

**`core/Controller.php`**:
- `current_user` auto-injection is removed. `current_employee` is now looked up directly via `AuthenticationManager::currentEmployee()` — no more two-step (`current_user` then a separate `EmployeeManager::findByUserId()` lookup), since there's only one entity now.

**`DashboardManager`**:
- `youProfile()`'s fallback branch (building a synthetic profile from `User` + `Customer` when no `Employee` matched) is deleted — every logged-in person now has a real `Employee` row by construction, so `you` is always backed by a real record.
- `overviewForCustomer()` simplifies: one `Employee::find($userId)` lookup instead of separately fetching `User` then cross-referencing `Employee`.

## Template changes

- `templates/components/header.twig`: already reads `current_employee.first_name`/`last_name`/`employee_id` — just drop the `current_user` fallback branch (no longer needed).
- `apps/Dashboard/templates/dashboard/index.twig`: `you` is always present now; the `{% else %} Coming soon {% endif %}` branch for a missing employee can be removed (kept only for the case where `you` itself is null, i.e., no logged-in employee at all, which the auth guard already prevents from reaching this page).

## Testing impact

- `tests/Support/SeedsBillingSchema.php`: drop the `users` CREATE TABLE block; add `password_hash`, `role`, `id_number` columns to the `employees` CREATE TABLE block; drop `user_id`; change the unique constraint from `(customer_id, email)` to `email` alone, add a unique constraint on `id_number`.
- `tests/employees/EmployeeModelTest.php`: same inline-schema update as above.
- `tests/Support/BuildsCustomerRegistrationData.php`: no shape change needed at the input level (it already builds `name`/`email`/`phone`/`cnic` fields for the registration form) — but any assertions downstream that read `$result['user']` need to read `$result['employee']` instead (see below).
- `tests/customers/CustomerManagerTest.php`: `finalizeRegistration()`'s return shape changes `'user' => $user` to `'employee' => $employee` (see Open Question below — resolved as `employee`). All assertions on `$result['user']->role`, `->email`, etc. move to `$result['employee']`. The existing "company customers get the owner role" test is deleted/rewritten since there's no more owner/admin split — a new assertion confirms both individual and company signups produce `role === 'admin'`.
- `tests/utilities/AuthenticationManagerTest.php`: rewrite to build `Employee` fixtures instead of `User` fixtures; `AuthenticationManager::attempt()`/`login()`/`currentEmployee()` assertions swap types accordingly.

## Resolved decisions (from Q&A)

- `id_number` stays optional for every employee (only globally unique when present).
- No invite/email flow this pass — admin sets a new employee's password directly at creation time (future employee-creation UI, out of scope here).
- Password is optional per employee (not every employee needs login access).
- Email is globally unique (matches today's `users.email` behavior) — login resolves unambiguously with no customer disambiguation needed.
- The bootstrap employee created at signup gets no department/designation placeholder — both left `null`.
- Migration history is consolidated: original schema migrations are rewritten to their final shape; redundant follow-up migrations are deleted; seeds are merged down to one per app.

## Confirmed decision

`finalizeRegistration()`'s return array currently has a `user` key; this spec renames it to `employee`. Checked: `CustomerController`'s plan-selection action only reads `$result['success']` and `$result['error']`, never `$result['user']` — the only consumer of that key is `CustomerManagerTest.php`, which gets updated per the Testing impact section above. No production code path is affected by the rename.
