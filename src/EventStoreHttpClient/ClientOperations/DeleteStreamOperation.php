<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient\ClientOperations;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\DeleteResult;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Task\DeleteResultTask;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreHttpClient\Http\RequestMethod;
use Psr\Http\Message\ResponseInterface;

/** @internal */
class DeleteStreamOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var bool */
    private $hardDelete;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->hardDelete = $hardDelete;
    }

    public function task(): DeleteResultTask
    {
        $headers = [];

        if ($this->hardDelete) {
            $headers['ES-HardDelete'] = 'true';
        }

        $request = $this->requestFactory->createRequest(
            RequestMethod::Delete,
            $this->uriFactory->createUri($this->baseUri . '/streams/' . urlencode($this->stream)),
            $headers
        );

        $promise = $this->sendAsyncRequest($request);

        return new DeleteResultTask($promise, function (ResponseInterface $response): DeleteResult {
            switch ($response->getStatusCode()) {
                case 401:
                    throw AccessDenied::toStream($this->stream);
                case 204:
                case 410:
                    return new DeleteResult();
                default:
                    throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
            }
        });
    }
}
