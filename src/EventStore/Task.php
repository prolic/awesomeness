<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Http\Promise\Promise;

/** @internal */
class Task
{
    protected $promise;
    protected $result;

    /** @internal */
    public function __construct(Promise $promise)
    {
        $this->promise = $promise;
    }

    public function wait(): void
    {
        $this->promise->wait(false);
    }

    public function isPending(): bool
    {
        return $this->promise->getState() === Promise::PENDING;
    }

    public function isCompleted(): bool
    {
        return $this->promise->getState() === Promise::FULFILLED;
    }

    public function isFaulted(): bool
    {
        return $this->promise->getState() === Promise::REJECTED;
    }
}
