<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Http\Promise\Promise;

/** @internal */
class Task
{
    /** @var Task|Promise */
    protected $promise;
    /** @var callable|null */
    protected $callback;
    /** @var mixed */
    protected $result;

    /**
     * @internal
     * @param Task|Promise $promise
     * @param callable|null $callback
     */
    public function __construct($promise, callable $callback = null)
    {
        $this->promise = $promise;
        $this->callback = $callback ?? function () {
            return null;
        };
    }

    /**
     * @return mixed
     * @throws \Throwable
     */
    public function result()
    {
        if (null === $this->result) {
            $callback = $this->callback;

            if ($this->promise instanceof Task) {
                $response = $this->promise->result();
            } else {
                $response = $this->promise->wait(true);
            }

            $this->result = $callback($response);
        }

        return $this->result;
    }

    public function continueWith(callable $callback, string $task = __CLASS__): Task
    {
        if (! class_exists($task) || ! is_subclass_of($task, self::class)) {
            throw new \InvalidArgumentException('Provided task class does not exist or is not a subclass of ' . self::class);
        }

        return new $task($this, $callback);
    }

    public function wait(): void
    {
        $this->promise->wait(false);
    }

    public function isPending(): bool
    {
        if ($this->promise instanceof Task) {
            return $this->promise->isPending();
        }

        return $this->promise->getState() === Promise::PENDING;
    }

    public function isCompleted(): bool
    {
        if ($this->promise instanceof Task) {
            return $this->promise->isCompleted();
        }

        return $this->promise->getState() === Promise::FULFILLED;
    }

    public function isFaulted(): bool
    {
        if ($this->promise instanceof Task) {
            return $this->promise->isFaulted();
        }

        return $this->promise->getState() === Promise::REJECTED;
    }
}
