<?php

declare(strict_types=1);

namespace Prooph\HttpEventStore\Stats;

use Http\Client\HttpClient;
use Http\Message\RequestFactory;
use Http\Message\UriFactory;
use Prooph\EventStore\Stats\EventStoreStats;
use Prooph\EventStore\UserCredentials;
use Prooph\HttpEventStore\ConnectionSettings;
use Prooph\HttpEventStore\Stats\ClientOperations\StatsOperation;

final class HttpEventStoreStats implements EventStoreStats
{
    /** @var HttpClient */
    private $httpClient;
    /** @var RequestFactory */
    private $requestFactory;
    /** @var UriFactory */
    private $uriFactory;
    /** @var ConnectionSettings */
    private $settings;
    /** @var string */
    private $baseUri;

    public function __construct(
        HttpClient $httpClient,
        RequestFactory $requestFactory,
        UriFactory $uriFactory,
        ConnectionSettings $settings = null
    ) {
        $this->httpClient = $httpClient;
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

    public function getAll(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('', $userCredentials);
    }

    public function getProc(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('/proc', $userCredentials);
    }

    public function getReplication(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('/replication', $userCredentials);
    }

    public function getTcp(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('/tcp', $userCredentials);
    }

    public function getSys(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('/sys', $userCredentials);
    }

    public function getEs(UserCredentials $userCredentials = null): array
    {
        return $this->fetchStats('/es', $userCredentials);
    }

    private function fetchStats(string $section, ?UserCredentials $userCredentials): array
    {
        return (new StatsOperation())(
            $this->httpClient,
            $this->requestFactory,
            $this->uriFactory,
            $this->baseUri,
            $section,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );
    }
}
