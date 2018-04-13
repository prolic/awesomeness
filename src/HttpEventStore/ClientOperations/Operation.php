<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\Exception as HttpException;
use Http\Client\HttpClient;
use Http\Message\Authentication\BasicAuth;
use Prooph\EventStore\Exception\ConnectionException;
use Prooph\EventStore\UserCredentials;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal */
abstract class Operation
{
    protected function sendRequest(
        HttpClient $httpClient,
        ?UserCredentials $userCredentials,
        RequestInterface $request
    ): ResponseInterface {
        if ($userCredentials) {
            $auth = new BasicAuth($userCredentials->username(), $userCredentials->password());
            $request = $auth->authenticate($request);
        }

        try {
            return $httpClient->sendRequest($request);
        } catch (HttpException $e) {
            throw new ConnectionException($e->getMessage());
        } catch (\Exception $e) {
            throw new ConnectionException($e->getMessage());
        }
    }
}
