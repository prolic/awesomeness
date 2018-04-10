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
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $name,
        string $type,
        string $query,
        bool $emitEnabled,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($asyncClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->name = $name;
        $this->type = $type;
        $this->query = $query;
        $this->emitEnabled = $emitEnabled;
    }

    public function task(): Task
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

        $promise = $this->sendAsyncRequest($request);

        return new Task($promise, function (ResponseInterface $response): void {
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
        });
    }
}
