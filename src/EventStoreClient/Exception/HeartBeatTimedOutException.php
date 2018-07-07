<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

use Amp\TimeoutException;

class HeartBeatTimedOutException extends TimeoutException implements Exception
{
    public function __construct(string $message = 'Heartbeat timed out, connection closed')
    {
        parent::__construct($message);
    }
}
