<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class DeleteStreamOperation extends Operation
{
    /** @var string */
    private $stream;
    /** @var bool */
    private $hardDelete;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $stream,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->stream = $stream;
        $this->hardDelete = $hardDelete;
    }

    public function __invoke(): void
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

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 204:
            case 410:
                return;
            case 401:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
