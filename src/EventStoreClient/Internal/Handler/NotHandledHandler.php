<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Handler;

use Prooph\EventStoreClient\Internal\Data\NotHandled;
use Prooph\EventStoreClient\Internal\Data\NotHandled_MasterInfo;
use Prooph\EventStoreClient\Internal\Handler;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;

class NotHandledHandler implements Handler
{
    public function handle(MessageType $messageType, string $correlationId, string $data): SocketMessage
    {
        $dataObject = new NotHandled();
        $dataObject->mergeFromString($data);

        if (2 === $dataObject->getReason()) {
            $dataObject = new NotHandled_MasterInfo();
            $dataObject->mergeFromString($data);
        }

        return new SocketMessage($messageType, $correlationId, $dataObject);
    }
}
