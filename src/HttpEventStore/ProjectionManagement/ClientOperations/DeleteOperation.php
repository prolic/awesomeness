<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Task;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;
use Prooph\HttpEventStore\ProjectionManagement\ProjectionNotFound;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class DeleteOperation extends Operation
{
    /** @var string */
    private $name;
    /** @var bool */
    private $deleteStateStream;
    /** @var bool */
    private $deleteCheckpointStream;
    /** @var bool */
    private $deleteEmittedStreams;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->deleteStateStream = $deleteStateStream;
        $this->deleteCheckpointStream = $deleteCheckpointStream;
        $this->deleteEmittedStreams = $deleteEmittedStreams;
    }

    public function task(): Task
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Delete,
            $this->uriFactory->createUri(sprintf(
                $this->baseUri . '/projection/%s?deleteStateStream=%s&deleteCheckpointStream=%s&deleteEmittedStreams=%s',
                urlencode($this->name),
                (int) $this->deleteStateStream,
                (int) $this->deleteCheckpointStream,
                (int) $this->deleteEmittedStreams
            ))
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
            switch ($response->getStatusCode()) {
                case 204:
                    return;
                case 401:
                    throw AccessDenied::toUserManagementOperation();
                case 404:
                    throw new ProjectionNotFound();
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
