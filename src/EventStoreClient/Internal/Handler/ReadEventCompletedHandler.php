<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Handler;

use Prooph\EventStoreClient\Internal\Handler;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;
use Rxnet\EventStore\Data\ReadEventCompleted;

/**
 * Class ReadStreamEventsForwardCompleted
 * @package Madkom\EventStore\Client\Domain\Socket\Communication\Type
 * @author  Dariusz Gafka <d.gafka@madkom.pl>
 */
class ReadEventCompletedHandler implements Handler
{
    public function handle(MessageType $messageType, string $correlationId, string $data): SocketMessage
    {
        $dataObject = new ReadEventCompleted();
        $dataObject->mergeFromString($data);

        return new SocketMessage($messageType, $correlationId, $dataObject);
    }
}
