<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Handler;

use Prooph\EventStoreClient\Internal\Data\TransactionCommitCompleted;
use Prooph\EventStoreClient\Internal\Handler;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;

class TransactionCommitCompletedHandler implements Handler
{
    public function handle(MessageType $messageType, string $correlationId, string $data): SocketMessage
    {
        $dataObject = new TransactionCommitCompleted();
        $dataObject->mergeFromString($data);

        return new SocketMessage($messageType, $correlationId, $dataObject);
    }
}
