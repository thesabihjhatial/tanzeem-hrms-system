<?php

// Tanzeem HRMS System Database Manager developed and maintained by Sabih

namespace App\Utilities;

use PDO;
use PDOException;

class DatabaseManager
{

    private static ?PDO $connection = null;

    public static function connect(array $config): PDO
    {

        if (self::$connection === null) {

            try {

                self::$connection = new PDO(
                    self::buildDsn($config),
                    $config['username'] ?? null,
                    $config['password'] ?? null,
                    [
                        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                    ],
                );

            } catch (PDOException $e) {

                throw new PDOException('Database connection failed: ' . $e->getMessage(), (int) $e->getCode());

            }

        }

        return self::$connection;

    }

    public static function select(string $query, array $params = []): array
    {

        $stmt = self::connection()->prepare($query);
        $stmt->execute($params);

        return $stmt->fetchAll();

    }

    public static function selectOne(string $query, array $params = []): ?array
    {

        return self::select($query, $params)[0] ?? null;

    }

    public static function execute(string $query, array $params = []): bool
    {

        return self::connection()->prepare($query)->execute($params);

    }

    public static function lastInsertId(): string
    {

        return self::connection()->lastInsertId();

    }

    public static function beginTransaction(): bool
    {

        return self::connection()->beginTransaction();

    }

    public static function commit(): bool
    {

        return self::connection()->commit();

    }

    public static function rollBack(): bool
    {

        return self::connection()->rollBack();

    }

    public static function reset(): void
    {

        self::$connection = null;

    }

    private static function connection(): PDO
    {

        if (self::$connection === null) {

            throw new \RuntimeException('DatabaseManager::connect() must be called before running queries.');

        }

        return self::$connection;

    }

    private static function buildDsn(array $config): string
    {

        return match ($config['driver']) {

            'sqlite' => 'sqlite:' . $config['database'],
            default => sprintf(
                'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
                $config['host'],
                $config['port'],
                $config['database'],
            ),

        };

    }

}
