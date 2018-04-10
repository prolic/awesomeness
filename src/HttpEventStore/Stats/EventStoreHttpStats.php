<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\Stats;

use Http\Client\HttpAsyncClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Stats\EventStoreStats;
use Prooph\EventStore\Task\GetArrayTask;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ConnectionSettings;
use Prooph\HttpEventStore\Stats\ClientOperations\StatsOperation;

final class EventStoreHttpStats implements EventStoreStats
{
    /** @var HttpAsyncClient */
    private $asyncClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpAsyncClient $asyncClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null
    ) {
        $this->asyncClient = $asyncClient;
        $this->requestFactory = $requestFactory;
        $this->uriFactory = $uriFactory;
        $this->settings = $settings ?? ConnectionSettings::default();
        $this->baseUri = sprintf(
            '%s://%s:%s',
            $this->settings->useSslConnection() ? 'https' : 'http',
            $this->settings->endPoint()->host(),
            $this->settings->endPoint()->port()
        );
    }

    public function getAll(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('', $userCredentials);

        return $operation->task();
    }

    public function getProc(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('/proc', $userCredentials);

        return $operation->task();
    }

    public function getReplication(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('/replication', $userCredentials);

        return $operation->task();
    }

    public function getTcp(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('/proc/tcp', $userCredentials);

        return $operation->task();
    }

    public function getSys(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('/sys', $userCredentials);

        return $operation->task();
    }

    public function getEs(UserCredentials $userCredentials = null): GetArrayTask
    {
        $operation = $this->statsOperation('/es', $userCredentials);

        return $operation->task();
    }

    private function statsOperation(string $section, ?UserCredentials $userCredentials): StatsOperation
    {
        return new StatsOperation(
            $this->asyncClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $section,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }
}
