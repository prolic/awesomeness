<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Http\Promise\Promise;

class Task
{
    protected $promise;
    protected $result;

    /** @internal */
    public function __construct(Promise $promise)
    {
        $this->promise = $promise;

        $promise->then(
            function ($result): void {
                $this->result = $result;
            },
            function ($result): void {
                $this->result = $result;
            }
        );
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
