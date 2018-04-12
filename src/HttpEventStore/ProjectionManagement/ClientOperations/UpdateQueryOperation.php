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

/** @internal */
class UpdateQueryOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var string */
    private $type;
    /** @var string */
    private $query;
    /** @var bool */
    private $emitEnabled;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->type = $type;
        $this->query = $query;
        $this->emitEnabled = $emitEnabled;
    }

    public function __invoke(): void
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Put,
                $this->uriFactory->createUri(sprintf(
                    $this->baseUri . '/projection/%s/query?type=%s&emit=%s',
                    urlencode($this->name),
                    $this->type,
                    (int) $this->emitEnabled
                )),
            [
                'Content-Type' => 'application/json',
            ],
            $this->query
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
