<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class MaxQueueSizeLimitReachedException extends \RuntimeException implements Exception
{
    public static function with(string $connectionName, int $maxQueueSize): MaxQueueSizeLimitReachedException
    {
        return new self(
            \sprintf(
                'EventStoreConnection \'%s\': reached max queue size limit: \'%s\'',
                $connectionName,
                $maxQueueSize
            )
        );
    }
}
