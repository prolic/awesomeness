<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;

interface Handler
{
    public function handle(MessageType $messageType, string $correlationID, string $data): SocketMessage;
}
