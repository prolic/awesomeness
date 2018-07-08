<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;

class ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var bool */
    private $isCluster;
    /** @var bool */
    private $useSslConnection;
    /** @var bool */
    private $requireMaster;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;
    /** @var int */
    private $operationTimeout;
    /** @var int */
    private $heartbeatInterval;
    /** @var int */
    private $heartbeatTimeout;

    public static function default(): ConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 1113),
            false,
            false,
            true,
            1000,
            2500,
            1500,
            null
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        bool $isCluster,
        bool $useSslConnection,
        bool $requireMaster,
        int $operationTimeout,
        int $heartbeatInterval,
        int $heartbeatTimeout,
        UserCredentials $defaultUserCredentials = null
    ) {
        if ($heartbeatInterval >= 5000) {
            throw new InvalidArgumentException('Heartbeat interval must be less then 5000ms');
        }

        $this->endPoint = $endpoint;
        $this->isCluster = $isCluster;
        $this->useSslConnection = $useSslConnection;
        $this->requireMaster = $requireMaster;
        $this->operationTimeout = $operationTimeout;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->heartbeatTimeout = $heartbeatTimeout;
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

    public function isCluster(): bool
    {
        return $this->isCluster;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }

    public function operationTimeout(): int
    {
        return $this->operationTimeout;
    }

    public function heartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    public function heartbeatTimeout(): int
    {
        return $this->heartbeatTimeout;
    }

    public function uri(): string
    {
        if ($this->isCluster) {
            $format = 'discover://%s:%s';
        } else {
            $format = 'tcp://%s:%s';
        }

        return \sprintf($format, $this->endPoint->host(), $this->endPoint->port());
    }
}
