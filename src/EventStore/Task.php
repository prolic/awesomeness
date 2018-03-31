<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Http\Promise\Promise;

/** @internal */
class Task
{
    /** @var Promise */
    protected $promise;
    /** @var callable|null */
    protected $callback;

    /** @internal */
    public function __construct(Promise $promise, callable $callback = null)
    {
        $this->promise = $promise;
        $this->callback = $callback;
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
