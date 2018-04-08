<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\Stats\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStoreClient\Exception\AccessDenied;
use Prooph\EventStoreClient\Task\GetArrayTask;
use Prooph\EventStoreClient\UserCredentials;
use Prooph\EventStoreHttpClient\ClientOperations\Operation;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class StatsOperation extends Operation
{
    /** @var string */
    private $section;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $section,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->section = $section;
    }

    public function task(): GetArrayTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/stats' . $this->section)
        );

        $promise = $this->sendAsyncRequest($request);

        return new GetArrayTask($promise, function (ResponseInterface $response): array {
            switch ($response->getStatusCode()) {
                case 200:
                    return json_decode($response->getBody()->getContents(), true);
                case 401:
                    throw AccessDenied::toUserManagementOperation();
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
