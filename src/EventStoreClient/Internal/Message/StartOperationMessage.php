<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\Internal\ClientOperations\ClientOperation;
use Prooph\EventStoreClient\Internal\Message;

/** @internal  */
class StartOperationMessage implements Message
{
    /** @var ClientOperation */
    private $operation;
    /** @var int */
    private $maxRetries;
    /** @var int */
    private $timeout;

    public function __construct(ClientOperation $operation, int $maxRetries, int $timeout)
    {
        $this->operation = $operation;
        $this->maxRetries = $maxRetries;
        $this->timeout = $timeout;
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function timeout(): int
    {
        return $this->timeout;
    }
}
