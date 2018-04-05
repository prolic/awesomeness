<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\EventData;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\WriteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStore\WriteResult;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class GetInformationForAllSubscriptionsOperation extends Operation
{
    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);
    }

    public function task(): Task
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/subscriptions')
        );

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
            $json = json_decode($response->getBody()->getContents(), true);

            var_dump($response->getStatusCode(), $json);
            die;

            switch ($response->getStatusCode()) {
                case 400:
                    $header = $response->getHeader('ES-CurrentVersion');

                    if (empty($header)) {
                        throw WrongExpectedVersion::withExpectedVersion($this->stream, $this->expectedVersion);
                    }
                        $currentVersion = (int) $header[0];

                    throw WrongExpectedVersion::withCurrentVersion($this->stream, $this->expectedVersion, $currentVersion);
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 410:
                    throw StreamDeleted::with($this->stream);
                case 201:
                    $nextExpectedVersion = $this->expectedVersion + count($this->events) + 1;

                    //return new WriteResult($nextExpectedVersion);
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
