<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

class DeleteStreamCompleted
{
    private $result;
    private $message;
    private $preparePosition;
    private $commitPosition;

    public function __construct(OperationResult $result, string $message, ?int $preparePosition, ?int $commitPosition)
    {
        $this->result = $result;
        $this->message = $message;
        $this->preparePosition = $preparePosition;
        $this->commitPosition = $commitPosition;
    }

    public function result(): OperationResult
    {
        return $this->result;
    }

    public function message(): string
    {
        return $this->message;
    }

    public function preparePosition(): ?int
    {
        return $this->preparePosition;
    }

    public function commitPosition(): ?int
    {
        return $this->commitPosition;
    }
}
