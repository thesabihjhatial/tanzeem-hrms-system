<?php

// Tanzeem HRMS System Log Manager developed and maintained by Sabih

namespace App\Utilities;

class LogManager
{

    public const LEVEL_ERROR = 'error';

    public const LEVEL_INFO = 'info';

    public const LEVEL_WARNING = 'warning';

    private static bool $fallbackGuard = false;

    /** @param array<string, mixed> $context */
    public static function log(string $level, string $source, string $message, array $context = []): void
    {

        try {

            DatabaseManager::execute(
                'INSERT INTO logs (level, source, message, context, created_at) VALUES (?, ?, ?, ?, ?)',
                [$level, $source, $message, $context === [] ? null : json_encode($context), date('Y-m-d H:i:s')],
            );

        } catch (\Throwable $e) {

            self::fallback($level, $source, $message, $e);

        }

    }

    /** @param array<string, mixed> $context */
    public static function info(string $source, string $message, array $context = []): void
    {

        self::log(self::LEVEL_INFO, $source, $message, $context);

    }

    /** @param array<string, mixed> $context */
    public static function warning(string $source, string $message, array $context = []): void
    {

        self::log(self::LEVEL_WARNING, $source, $message, $context);

    }

    /** @param array<string, mixed> $context */
    public static function error(string $source, string $message, array $context = []): void
    {

        self::log(self::LEVEL_ERROR, $source, $message, $context);

    }

    private static function fallback(string $level, string $source, string $message, \Throwable $loggingError): void
    {

        if (self::$fallbackGuard) {

            return;

        }

        self::$fallbackGuard = true;

        $line = sprintf(
            "[%s] (logs table unavailable: %s) [%s] %s: %s\n",
            date('c'),
            $loggingError->getMessage(),
            strtoupper($level),
            $source,
            $message,
        );

        file_put_contents(__DIR__ . '/../storage/logs/error.log', $line, FILE_APPEND);

        self::$fallbackGuard = false;

    }

}
