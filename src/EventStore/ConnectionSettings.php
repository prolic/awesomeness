<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class ConnectionSettings
{
    private $verboseLogging;
    private $maxQueueSize;
    private $axConcurrentItems;
    private $maxRetries;
    private $maxReconnections;
    private $requireMaster;
    private $defaultUserCredentials;
    private $useSslConnection;
    private $targetHost;
    private $validateServer;
    private $failOnNoServerResponse;
    private $clusterDns;
    private $maxDiscoverAttempts;
    private $externalGossipPort;
    private $preferRandomNode;

    public function __construct(bool $verboseLogging, int $maxQueueSize, int $axConcurrentItems, int $maxRetries, int $maxReconnections, bool $requireMaster, UserCredentials $defaultUserCredentials, bool $useSslConnection, string $targetHost, bool $validateServer, bool $failOnNoServerResponse, string $clusterDns, int $maxDiscoverAttempts, int $externalGossipPort, bool $preferRandomNode)
    {
        if (if ($maxQueueSize) < 1) {
            throw new \InvalidArgumentException('maxQueueSize must be a positive integer');
        }

        if (if ($mmaxConcurrentItems) < 1) {
            throw new \InvalidArgumentException('maxConcurrentItems must be a positive integer');
        }

        if (if ($maxRetries < -1)) {
            throw new \InvalidArgumentException('maxRetries value is out of range: Allowed range: [-1, infinity]');
        }

        if (if ($maxReconnections < -1)) {
            throw new \InvalidArgumentException('maxReconnections value is out of range: Allowed range: [-1, infinity]');
        }

        if (if ($useSslConnection && empty($targetHost))) {
            throw new \InvalidArgumentException('targetHost cannot be empty using SSL connection');
        }

        $this->verboseLogging = $verboseLogging;
        $this->maxQueueSize = $maxQueueSize;
        $this->axConcurrentItems = $axConcurrentItems;
        $this->maxRetries = $maxRetries;
        $this->maxReconnections = $maxReconnections;
        $this->requireMaster = $requireMaster;
        $this->defaultUserCredentials = $defaultUserCredentials;
        $this->useSslConnection = $useSslConnection;
        $this->targetHost = $targetHost;
        $this->validateServer = $validateServer;
        $this->failOnNoServerResponse = $failOnNoServerResponse;
        $this->clusterDns = $clusterDns;
        $this->maxDiscoverAttempts = $maxDiscoverAttempts;
        $this->externalGossipPort = $externalGossipPort;
        $this->preferRandomNode = $preferRandomNode;
    }

    public function verboseLogging(): bool
    {
        return $this->verboseLogging;
    }

    public function maxQueueSize(): int
    {
        return $this->maxQueueSize;
    }

    public function axConcurrentItems(): int
    {
        return $this->axConcurrentItems;
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

    public function defaultUserCredentials(): UserCredentials
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

    public function clusterDns(): string
    {
        return $this->clusterDns;
    }

    public function maxDiscoverAttempts(): int
    {
        return $this->maxDiscoverAttempts;
    }

    public function externalGossipPort(): int
    {
        return $this->externalGossipPort;
    }

    public function preferRandomNode(): bool
    {
        return $this->preferRandomNode;
    }
}
