<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\CreateProjectionResult;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class CreateTransientOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var string */
    private $type;
    /** @var string */
    private $query;
    /** @var bool */
    private $enabled;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        string $type,
        string $query,
        bool $enabled,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->type = $type;
        $this->query = $query;
        $this->enabled = $enabled;
    }

    public function __invoke(): CreateProjectionResult
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri(sprintf(
                $this->baseUri . '/projections/transient?name=%s&enabled=%s&type=%s',
                urlencode($this->name),
                (int) $this->enabled,
                $this->type
            )),
            [
                'Content-Type' => 'application/json',
            ],
            $this->query
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 201:
                return CreateProjectionResult::success();
            case 401:
                throw AccessDenied::toUserManagementOperation();
            case 409:
                return CreateProjectionResult::conflict();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
