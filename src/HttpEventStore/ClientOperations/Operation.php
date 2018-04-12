<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\Authentication\BasicAuth;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\Promise;
use Prooph\EventStore\UserCredentials;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ResponseInterface;

/** @internal */
abstract class Operation
{
    /** @var HttpClient */
    protected $httpClient;
    /** @var RequestFactory */
    protected $requestFactory;
    /** @var UriFactory */
    protected $uriFactory;
    /** @var string */
    protected $baseUri;
    /** @var UserCredentials|null */
    protected $userCredentials;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ) {
        $this->httpClient = $httpClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->baseUri = $baseUri;
        $this->userCredentials = $userCredentials;
    }

    protected function sendRequest(RequestInterface $request): ResponseInterface
    {
        if ($this->userCredentials) {
            $auth = new BasicAuth($this->userCredentials->username(), $this->userCredentials->password());
            $request = $auth->authenticate($request);
        }

        return $this->httpClient->sendRequest($request);
    }
}
