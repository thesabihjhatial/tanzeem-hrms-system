<?php

namespace App\Tests\Support;

use PDO;

trait SeedsBillingSchema
{
    protected function seedBillingSchema(PDO $pdo, bool $withDefaultPlan = true): void
    {
        $pdo->exec(<<<SQL
            CREATE TABLE customers (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                company_name TEXT NOT NULL,
                customer_type TEXT NOT NULL DEFAULT 'individual',
                phone TEXT NOT NULL,
                province TEXT NOT NULL,
                city TEXT NOT NULL,
                employee_count_range TEXT NOT NULL,
                ntn TEXT,
                eobi_registration_no TEXT,
                pessi_registration_no TEXT,
                business_type TEXT,
                created_at TEXT
            )
            SQL);

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

        $pdo->exec(<<<SQL
            CREATE TABLE otps (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                otp_hash TEXT NOT NULL,
                attempts INTEGER NOT NULL DEFAULT 0,
                payload TEXT NOT NULL,
                expires_at TEXT NOT NULL,
                created_at TEXT
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE login_throttles (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                email TEXT NOT NULL UNIQUE,
                attempts INTEGER NOT NULL DEFAULT 0,
                locked_until TEXT,
                updated_at TEXT
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE employees (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                uuid TEXT NOT NULL,
                customer_id INTEGER NOT NULL,
                employee_id TEXT,
                email TEXT NOT NULL,
                password_hash TEXT,
                role TEXT NOT NULL DEFAULT 'viewer',
                created_at TEXT,
                UNIQUE (email)
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE employee_info (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                employee_id INTEGER NOT NULL UNIQUE,
                first_name TEXT NOT NULL,
                last_name TEXT NOT NULL,
                department TEXT,
                designation TEXT,
                phone TEXT,
                city TEXT,
                id_number TEXT UNIQUE,
                photo TEXT,
                created_at TEXT
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE plans (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                name TEXT NOT NULL,
                price REAL NOT NULL,
                employee_limit INTEGER NOT NULL,
                billing_cycle TEXT NOT NULL,
                is_default INTEGER NOT NULL DEFAULT 0,
                created_at TEXT
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE subscriptions (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                plan_id INTEGER NOT NULL,
                status TEXT NOT NULL,
                trial_ends_at TEXT,
                current_period_end TEXT,
                created_at TEXT
            )
            SQL);

        $pdo->exec(<<<SQL
            CREATE TABLE invoices (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                customer_id INTEGER NOT NULL,
                subscription_id INTEGER NOT NULL,
                amount REAL NOT NULL,
                status TEXT NOT NULL,
                issued_at TEXT,
                paid_at TEXT
            )
            SQL);

        if ($withDefaultPlan) {
            $pdo->exec(<<<SQL
                INSERT INTO plans (name, price, employee_limit, billing_cycle, is_default, created_at) VALUES
                ('Free', 0, 3, 'monthly', 1, '2026-01-01 00:00:00'),
                ('Starter', 2500, 10, 'monthly', 0, '2026-01-01 00:00:00'),
                ('Growth', 5000, 25, 'monthly', 0, '2026-01-01 00:00:00'),
                ('Business', 10000, 50, 'monthly', 0, '2026-01-01 00:00:00')
                SQL);
        }
    }
}
