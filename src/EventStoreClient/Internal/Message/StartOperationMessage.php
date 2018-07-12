<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

/** @internal  */
class StartOperationMessage
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
