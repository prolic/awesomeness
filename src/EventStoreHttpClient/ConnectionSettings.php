<?php

declare(strict_types=1);

namespace Prooph\EventStoreHttpClient;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

class ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var bool */
    private $useSslConnection;
    /** @var bool */
    private $validateServer;
    /** @var Logger */
    private $logger;
    /** @var bool */
    private $verboseLogging;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;

    public static function default(): ConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 2113),
            false,
            false,
            new NullLogger(),
            false,
            null
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        bool $useSslConnection,
        bool $validateServer,
        Logger $logger,
        bool $verboseLogging,
        UserCredentials $defaultUserCredentials = null
    ) {
        $this->endPoint = $endpoint;
        $this->useSslConnection = $useSslConnection;
        $this->validateServer = $validateServer;
        $this->logger = $logger;
        $this->verboseLogging = $verboseLogging;
        $this->defaultUserCredentials = $defaultUserCredentials;
    }

    public function verboseLogging(): bool
    {
        return $this->verboseLogging;
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

    public function validateServer(): bool
    {
        return $this->validateServer;
    }
}
