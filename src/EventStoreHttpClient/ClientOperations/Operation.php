<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Message\Authentication\BasicAuth;
use Prooph\EventStore\UserCredentials;
use Psr\Http\Message\RequestInterface;

abstract class Operation
{
    protected function authenticate(RequestInterface $request, ?UserCredentials $userCredentials): RequestInterface
    {
        if ($userCredentials) {
            $auth = new BasicAuth($userCredentials->username(), $userCredentials->password());
            $request = $auth->authenticate($request);
        }

        return $request;
    }
}
