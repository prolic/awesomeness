<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;

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

    public static function default(): ConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 1113),
            false,
            false,
            true,
            null
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        bool $isCluster,
        bool $useSslConnection,
        bool $requireMaster,
        UserCredentials $defaultUserCredentials = null
    ) {
        $this->endPoint = $endpoint;
        $this->isCluster = $isCluster;
        $this->useSslConnection = $useSslConnection;
        $this->requireMaster = $requireMaster;
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
