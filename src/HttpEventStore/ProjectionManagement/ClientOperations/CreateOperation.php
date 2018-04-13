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
    public function __invoke(
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
    ): CreateProjectionResult {
        $request = $requestFactory->createRequest(
            RequestMethod::Post,
            $uriFactory->createUri(sprintf(
                $baseUri . '/projections/%s?name=%s&enabled=%s&checkpoints=%semit=%s&type=%s&trackemittedstreams=%s',
                $mode,
                urlencode($name),
                (int) $enabled,
                (int) $checkpoints,
                (int) $emit,
                $type,
                (int) $trackEmittedStreams
            )),
            [
                'Content-Type' => 'application/json',
            ],
            $query
        );

        $response = $this->sendRequest($httpClient, $userCredentials, $request);

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
