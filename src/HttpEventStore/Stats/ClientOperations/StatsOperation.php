<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\Stats\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class StatsOperation extends Operation
{
    /** @var string */
    private $section;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $section,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->section = $section;
    }

    public function __invoke(): array
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/stats' . $this->section)
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                return json_decode($response->getBody()->getContents(), true);
            case 401:
                throw AccessDenied::toUserManagementOperation();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
