<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ResetOperation extends Operation
{
    /** @var string */
    private $name;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
    }

    public function __invoke(): void
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/command/reset')
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                return;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            case 404:
                throw new ProjectionNotFound();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
