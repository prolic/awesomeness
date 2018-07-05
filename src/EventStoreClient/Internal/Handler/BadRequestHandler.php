<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Handler;

use Prooph\EventStoreClient\Exception\EventStoreHandlerException;
use Prooph\EventStoreClient\Internal\Handler;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;

class BadRequestHandler implements Handler
{
    public function handle(MessageType $messageType, string $correlationId, string $data): SocketMessage
    {
        throw new EventStoreHandlerException("Bad Request: $data");
    }
}
