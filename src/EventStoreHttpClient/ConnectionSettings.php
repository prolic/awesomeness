<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;

class ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var bool */
    private $useSslConnection;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;

    public static function default(): ConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 2113),
            false,
            null
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        bool $useSslConnection,
        UserCredentials $defaultUserCredentials = null
    ) {
        $this->endPoint = $endpoint;
        $this->useSslConnection = $useSslConnection;
        $this->defaultUserCredentials = $defaultUserCredentials;
    }

    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
    }

    public function useSslConnection(): bool
    {
        return $this->useSslConnection;
    }

    public function endPoint(): IpEndPoint
    {
        return $this->endPoint;
    }
}
