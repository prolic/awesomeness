<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class DeleteStreamCompleted
{
    private $result;
    private $message;

    public function __construct(OperationResult $result, string $message)
    {
        $this->result = $result;
        $this->message = $message;
    }

    public function result(): OperationResult
    {
        return $this->result;
    }

    public function message(): string
    {
        return $this->message;
    }
}
