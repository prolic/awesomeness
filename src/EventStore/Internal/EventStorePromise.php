<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

use Http\Client\Exception as HttplugException;
use Http\Promise\Promise;
use Psr\Http\Message\ResponseInterface;

/** @internal */
final class EventStorePromise implements Promise
{
    /** @var callable */
    private $callback;
    /** @var Promise */
    private $promise;
    /** @var string */
    private $state;
    /** @var ResponseInterface */
    private $response;
    /** @var mixed */
    private $result;
    /** @var \Exception */
    private $exception;

    /** @internal */
    public function __construct(Promise $promise, callable $callback)
    {
        $this->state = self::PENDING;
        $this->callback = $callback;
        $this->promise = $promise->then(function ($response) use ($callback) {
            $this->response = $response;
            $this->result = $callback($response);
            $this->state = self::FULFILLED;

            return $response;
        }, function ($reason) {
            $this->state = self::REJECTED;
            if ($reason instanceof HttplugException) {
                $this->exception = $reason;
            } elseif ($reason instanceof \Exception) {
                $this->exception = new \RuntimeException('Invalid exception returned from async client', 0, $reason);
            } else {
                $this->exception = new \UnexpectedValueException('Reason returned from async client must be an Exception', 0, $reason);
            }

            throw $this->exception;
        });
    }

    public function then(callable $onFulfilled = null, callable $onRejected = null)
    {
        // we don't accept that
    }

    public function getState()
    {
        return $this->state;
    }

    public function wait($unwrap = true)
    {
        $this->promise->wait(false);

        if ($unwrap) {
            if ($this->getState() == self::REJECTED) {
                throw $this->exception;
            }

            return $this->result;
        }
    }
}
