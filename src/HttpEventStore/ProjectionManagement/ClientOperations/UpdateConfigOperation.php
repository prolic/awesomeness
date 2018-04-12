<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\ProjectionConfig;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;

/** @internal */
class UpdateConfigOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var ProjectionConfig */
    private $config;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        ProjectionConfig $definition,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->config = $definition;
    }

    public function __invoke(): void
    {
        $string = json_encode($this->config->toArray());

        $request = $this->requestFactory->createRequest(
            RequestMethod::Put,
            $this->uriFactory->createUri($this->baseUri . '/projection/' . urlencode($this->name) . '/config'),
            [
                'Content-Type' => 'application/json',
                'Content-Length' => strlen($string),
            ],
            $string
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
