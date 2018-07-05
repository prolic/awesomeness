<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Handler;

use Prooph\EventStoreClient\Internal\Data\SubscriptionDropped;
use Prooph\EventStoreClient\Internal\Handler;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;

class SubscriptionDroppedHandler implements Handler
{
    /**
     * {@inheritdoc}
     */
    public function handle(MessageType $messageType, string $correlationId, string $data): SocketMessage
    {
        $dataObject = new SubscriptionDropped();
        $dataObject->mergeFromString($data);

        return new SocketMessage($messageType, $correlationId, $dataObject);
    }
}
