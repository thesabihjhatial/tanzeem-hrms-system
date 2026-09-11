<?php

// Tanzeem HRMS System Exception Manager developed and maintained by Sabih

namespace App\Utilities;

use App\Core\Response;

class ExceptionManager
{

    public const ERROR_STATUS = 500;

    public const GENERIC_MESSAGE = 'Internal server error. The incident has been logged for investigation.';

    public static function handle(\Throwable $e, bool $debug): Response
    {

        LogManager::error(
            (new \ReflectionClass($e))->getShortName(),
            $e->getMessage(),
            ['file' => $e->getFile(), 'line' => $e->getLine(), 'trace' => $e->getTraceAsString()],
        );

        $body = $debug
            ? sprintf('Error: %s in %s:%d', $e->getMessage(), $e->getFile(), $e->getLine())
            : self::GENERIC_MESSAGE;

        return Response::html($body, self::ERROR_STATUS);

    }

}
