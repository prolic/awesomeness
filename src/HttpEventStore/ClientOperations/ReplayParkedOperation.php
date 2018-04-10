<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\ReplayParkedResult;
use Prooph\EventStore\Internal\ReplayParkedStatus;
use Prooph\EventStore\Task;
use Prooph\EventStore\Task\ReplayParkedTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class ReplayParkedOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->groupName = $groupName;
    }

    public function task(): ReplayParkedTask
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Post,
            $this->uriFactory->createUri(sprintf(
                '%s/subscriptions/%s/%s/replayParked',
                $this->baseUri,
                urlencode($this->stream),
                urlencode($this->groupName)
            )),
            [
                'Content-Length' => 0,
            ],
            ''
        );

        $promise = $this->sendAsyncRequest($request);

        return new ReplayParkedTask($promise, function (ResponseInterface $response): ReplayParkedResult {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 404:
                case 200:
                    $json = json_decode($response->getBody()->getContents(), true);

                    return new ReplayParkedResult(
                        $json['correlationId'],
                        $json['reason'],
                        ReplayParkedStatus::byName($json['result'])
                    );
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
