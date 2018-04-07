<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\Authentication\BasicAuth;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Http\Promise\Promise;
use Prooph\EventStoreClient\UserCredentials;
use Psr\Http\Message\RequestInterface;

/** @internal */
abstract class Operation
{
    /** @var HttpAsyncClient */
    protected $asyncClient;
    /** @var RequestFactory */
    protected $requestFactory;
    /** @var UriFactory */
    protected $uriFactory;
    /** @var string */
    protected $baseUri;
    /** @var UserCredentials|null */
    protected $userCredentials;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ) {
        $this->asyncClient = $asyncClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->baseUri = $baseUri;
        $this->userCredentials = $userCredentials;
    }

    protected function sendAsyncRequest(RequestInterface $request): Promise
    {
        if ($this->userCredentials) {
            $auth = new BasicAuth($this->userCredentials->username(), $this->userCredentials->password());
            $request = $auth->authenticate($request);
        }

        return $this->asyncClient->sendAsyncRequest($request);
    }
}
