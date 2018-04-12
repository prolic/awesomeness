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

/** @internal */
class CreateOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var string */
    private $mode;
    /** @var string */
    private $type;
    /** @var string */
    private $query;
    /** @var bool */
    private $enabled;
    /** @var bool */
    private $checkpoints;
    /** @var bool */
    private $emit;
    /** @var bool */
    private $trackEmittedStreams;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        string $mode,
        string $type,
        string $query,
        bool $enabled,
        bool $checkpoints,
        bool $emit,
        bool $trackEmittedStreams,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->mode = $mode;
        $this->type = $type;
        $this->query = $query;
        $this->enabled = $enabled;
        $this->checkpoints = $checkpoints;
        $this->emit = $emit;
        $this->trackEmittedStreams = $trackEmittedStreams;
    }

    public function __invoke(): CreateProjectionResult
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri(sprintf(
                $this->baseUri . '/projections/%s?name=%s&enabled=%s&checkpoints=%semit=%s&type=%s&trackemittedstreams=%s',
                $this->mode,
                urlencode($this->name),
                (int) $this->enabled,
                (int) $this->checkpoints,
                (int) $this->emit,
                $this->type,
                (int) $this->trackEmittedStreams
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
