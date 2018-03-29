<?php

declare(strict_types=1);

namespace Prooph\EventStore\Messages;

/** @internal */
class TransactionWrite
{
    /** @var int */
    private $transactionId;
    /** @var NewEvent[] */
    private $events;
    /** @var bool */
    private $requireMaster;

    public function __construct(int $transactionId, array $events, bool $requireMaster)
    {
        $this->transactionId = $transactionId;

        foreach ($events as $__value) {
            if (! $__value instanceof NewEvent) {
                throw new \InvalidArgumentException('events expected an array of Prooph\EventStore\Messages\NewEvent');
            }
            $this->events[] = $__value;
        }

        $this->requireMaster = $requireMaster;
    }

    public function transactionId(): int
    {
        return $this->transactionId;
    }

    public function events(): array
    {
        return $this->events;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }
}
