<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\ProjectionManagement\ClientOperations;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\ProjectionManagement\ProjectionDetails;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ClientOperations\Operation;
use Prooph\HttpEventStore\Http\RequestMethod;

/** @internal */
class GetMultiOperation extends Operation
{
    /** @var string */
    private $mode;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        string $baseUri,
        string $mode,
        ?UserCredentials $userCredentials
    ) {
        parent::__construct($httpClient, $requestFactory, $uriFactory, $baseUri, $userCredentials);

        $this->mode = $mode;
    }

    /**
     * @return ProjectionDetails[]
     */
    public function __invoke(): array
    {
        $request = $this->requestFactory->createRequest(
            RequestMethod::Get,
            $this->uriFactory->createUri($this->baseUri . '/projections/' . $this->mode)
        );

        $response = $this->sendRequest($request);

        switch ($response->getStatusCode()) {
            case 200:
                $json = json_decode($response->getBody()->getContents(), true);

                $result = [];

                foreach ($json['projections'] as $details) {
                    $result[] = new ProjectionDetails(
                        $details['coreProcessingTime'],
                        $details['version'],
                        $details['epoch'],
                        $details['effectiveName'],
                        $details['writesInProgress'],
                        $details['readsInProgress'],
                        $details['partitionsCached'],
                        $details['status'],
                        $details['stateReason'],
                        $details['name'],
                        $details['mode'],
                        $details['position'],
                        $details['progress'],
                        $details['lastCheckpoint'],
                        $details['eventsProcessedAfterRestart'],
                        $details['statusUrl'],
                        $details['stateUrl'],
                        $details['resultUrl'],
                        $details['queryUrl'],
                        $details['enableCommandUrl'],
                        $details['disableCommandUrl'],
                        $details['checkpointStatus'],
                        $details['bufferedEvents'],
                        $details['writePendingEventsBeforeCheckpoint'],
                        $details['writePendingEventsAfterCheckpoint']
                    );
                }

                return $result;
            case 401:
                throw AccessDenied::toUserManagementOperation();
            default:
                throw new \UnexpectedValueException('Unexpected status code ' . $response->getStatusCode() . ' returned');
        }
    }
}
