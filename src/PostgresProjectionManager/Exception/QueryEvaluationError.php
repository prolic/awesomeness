<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Exception;

/** @internal */
class QueryEvaluationError extends \RuntimeException implements Exception
{
    public static function with(string $message): QueryEvaluationError
    {
        return new self('QueryEvaluationError: ' . $message);
    }
}
