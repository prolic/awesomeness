<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Internal\Consts;
use Psr\Log\LoggerInterface as Logger;
use Psr\Log\NullLogger;

class ConnectionSettings
{
    private $logger;
    private $verboseLogging;
    private $maxQueueSize;
    private $maxConcurrentItems;
    private $maxRetries;
    private $maxReconnections;
    private $requireMaster;
    private $reconnectionDelay; // milliseconds
    private $operationTimeout; // seconds
    private $operationTimeoutCheckPeriod; // seconds
    private $defaultUserCredentials;
    private $useSslConnection;
    private $targetHost;
    private $validateServer;
    private $failOnNoServerResponse;
    private $heartbeatInterval; // milliseconds
    private $heartbeatTimeout; // milliseconds
    private $clusterDns;
    private $maxDiscoverAttempts;
    private $preferRandomNode;
    private $clientConnectionTimeout; // milliseconds
    private $externalGossipPort;
    private $gossipSeeds;
    private $gossipTimeout; // seconds

    public static function defaultSettings(): ConnectionSettings
    {
        return new self(
            new NullLogger(),
            false,
            Consts::DefaultMaxQueueSize,
            Consts::DefaultMaxConcurrentItems,
            Consts::DefaultMaxOperationRetries,
            Consts::DefaultMaxReconnections,
            Consts::DefaultRequireMaster,
            Consts::DefaultReconnectionDelay,
            Consts::DefaultOperationTimeout,
            Consts::DefaultOperationTimeoutCheckPeriod,
            null,
            false,
            '',
            false,
            true,
            750,
            1500,
            '',
            Consts::DefaultMaxClusterDiscoverAttempts,
            false,
            1000,
            Consts::DefaultClusterManagerExternalHttpPort,
            [],
            1
        );
    }

    /**
     * @param Logger $logger
     * @param bool $verboseLogging
     * @param int $maxQueueSize
     * @param int $maxConcurrentItems
     * @param int $maxRetries
     * @param int $maxReconnections
     * @param bool $requireMaster
     * @param int $reconnectionDelay
     * @param int $operationTimeout
     * @param int $operationTimeoutCheckPeriod
     * @param null|UserCredentials $defaultUserCredentials
     * @param bool $useSslConnection
     * @param string $targetHost
     * @param bool $validateServer
     * @param bool $failOnNoServerResponse
     * @param int $heartbeatInterval
     * @param int $heartbeatTimeout
     * @param string $clusterDns
     * @param int $maxDiscoverAttempts
     * @param bool $preferRandomNode
     * @param int $clientConnectionTimeout
     * @param int $externalGossipPort
     * @param GossipSeed[] $gossipSeeds
     * @param int $gossipTimeout
     */
    public function __construct(
        Logger $logger,
        bool $verboseLogging,
        int $maxQueueSize,
        int $maxConcurrentItems,
        int $maxRetries,
        int $maxReconnections,
        bool $requireMaster,
        int $reconnectionDelay, // milliseconds
        int $operationTimeout, // seconds
        int $operationTimeoutCheckPeriod, // seconds
        ?UserCredentials $defaultUserCredentials,
        bool $useSslConnection,
        string $targetHost,
        bool $validateServer,
        bool $failOnNoServerResponse,
        int $heartbeatInterval, // milliseconds
        int $heartbeatTimeout, // milliseconds
        string $clusterDns,
        int $maxDiscoverAttempts,
        bool $preferRandomNode,
        int $clientConnectionTimeout, // milliseconds
        int $externalGossipPort,
        array $gossipSeeds,
        int $gossipTimeout // seconds
    ) {
        if ($maxQueueSize < 1) {
            throw new \InvalidArgumentException('maxQueueSize must be a positive integer');
        }

        if ($maxConcurrentItems < 1) {
            throw new \InvalidArgumentException('maxConcurrentItems must be a positive integer');
        }

        if ($maxRetries < -1) {
            throw new \InvalidArgumentException('maxRetries value is out of range: Allowed range: [-1, infinity]');
        }

        if ($maxReconnections < -1) {
            throw new \InvalidArgumentException('maxReconnections value is out of range: Allowed range: [-1, infinity]');
        }

        if ($useSslConnection && empty($targetHost)) {
            throw new \InvalidArgumentException('targetHost cannot be empty using SSL connection');
        }

        $this->logger = $logger;
        $this->verboseLogging = $verboseLogging;
        $this->maxQueueSize = $maxQueueSize;
        $this->maxConcurrentItems = $maxConcurrentItems;
        $this->maxRetries = $maxRetries;
        $this->maxReconnections = $maxReconnections;
        $this->requireMaster = $requireMaster;
        $this->reconnectionDelay = $reconnectionDelay;
        $this->operationTimeout = $operationTimeout;
        $this->operationTimeoutCheckPeriod = $operationTimeoutCheckPeriod;
        $this->defaultUserCredentials = $defaultUserCredentials;
        $this->useSslConnection = $useSslConnection;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;
        $this->failOnNoServerResponse = $failOnNoServerResponse;
        $this->heartbeatInterval = $heartbeatInterval;
        $this->heartbeatTimeout = $heartbeatTimeout;
        $this->clusterDns = $clusterDns;
        $this->maxDiscoverAttempts = $maxDiscoverAttempts;
        $this->preferRandomNode = $preferRandomNode;
        $this->clientConnectionTimeout = $clientConnectionTimeout;
        $this->externalGossipPort = $externalGossipPort;
        $this->gossipTimeout = $gossipTimeout;

        foreach ($this->gossipSeeds as $seed) {
            if (! $seed instanceof GossipSeed) {
                throw new \InvalidArgumentException('Expected an array of ' . GossipSeed::class);
            }

            $this->gossipSeeds[] = $seed;
        }
    }

    public function verboseLogging(): bool
    {
        return $this->verboseLogging;
    }

    public function maxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    public function maxConcurrentItems(): int
    {
        return $this->maxConcurrentItems;
    }

    public function maxRetries(): int
    {
        return $this->maxRetries;
    }

    public function maxReconnections(): int
    {
        return $this->maxReconnections;
    }

    public function requireMaster(): bool
    {
        return $this->requireMaster;
    }

    public function reconnectionDelay(): int
    {
        return $this->reconnectionDelay;
    }

    public function operationTimeout(): int
    {
        return $this->operationTimeout;
    }

    public function operationTimeoutCheckPeriod(): int
    {
        return $this->operationTimeoutCheckPeriod;
    }

    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
    }

    public function useSslConnection(): bool
    {
        return $this->useSslConnection;
    }

    public function targetHost(): string
    {
        return $this->targetHost;
    }

    public function validateServer(): bool
    {
        return $this->validateServer;
    }

    public function failOnNoServerResponse(): bool
    {
        return $this->failOnNoServerResponse;
    }

    public function heartbeatInterval(): int
    {
        return $this->heartbeatInterval;
    }

    public function heartbeatTimeout(): int
    {
        return $this->heartbeatTimeout;
    }

    public function clusterDns(): string
    {
        return $this->clusterDns;
    }

    public function maxDiscoverAttempts(): int
    {
        return $this->maxDiscoverAttempts;
    }

    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }

    public function clientConnectionTimeout(): int
    {
        return $this->clientConnectionTimeout;
    }

    public function externalGossipPort(): int
    {
        return $this->externalGossipPort;
    }

    public function gossipSeeds()
    {
        return $this->gossipSeeds;
    }

    public function gossipTimeout(): int
    {
        return $this->gossipTimeout;
    }
}
