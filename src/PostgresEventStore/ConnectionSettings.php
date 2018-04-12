<?php

declare(strict_types=1);

namespace Prooph\PostgresEventStore;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;

// @todo add ssl support
class ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $dbName;
    /** @var UserCredentials */
    private $userCredentials;
    /** @var bool */
    private $persistent;
    /** @var string|null */
    private $sslmode;
    /** @var string|null */
    private $sslrootcert;
    /** @var string|null */
    private $sslcert;
    /** @var string|null */
    private $sslkey;
    /** @var string|null */
    private $sslcrl;
    /** @var string|null */
    private $applicationName;

    public static function default(): ConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 5432),
            'event_store',
            new UserCredentials('postgres', ''),
            false
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        string $dbName,
        UserCredentials $userCredentials,
        bool $persistent,
        string $sslmode = null,
        string $sslrootcert = null,
        string $sslcert = null,
        string $sslkey = null,
        string $sslcrl = null,
        string $applicationName = null
    ) {
        $this->endPoint = $endpoint;
        $this->dbName = $dbName;
        $this->userCredentials = $userCredentials;
        $this->persistent = $persistent;
        $this->sslmode = $sslmode;
        $this->sslrootcert = $sslrootcert;
        $this->sslcert = $sslcert;
        $this->sslkey = $sslkey;
        $this->sslcrl = $sslcrl;
        $this->applicationName = $applicationName;
    }

    public function connectionString(): string
    {
        $connectionString = "dbname=$this->dbName host={$this->endPoint->host()} port={$this->endPoint->port()} "
            . "user={$this->userCredentials->username()}";

        if ('' !== $this->userCredentials->password()) {
            $connectionString .= "  password={$this->userCredentials->password()}";
        }

        if (null !== $this->sslmode) {
            $connectionString .= " sslmode=$this->sslmode";
        }

        if (null !== $this->sslcert) {
            $connectionString .= " sslrootcert=$this->sslrootcert";
        }

        if (null !== $this->sslcert) {
            $connectionString .= " sslcert=$this->sslcert";
        }

        if (null !== $this->sslkey) {
            $connectionString .= " sslkey=$this->sslkey";
        }

        if (null !== $this->sslcrl) {
            $connectionString .= " sslcrl=$this->sslcrl";
        }

        if (null !== $this->applicationName) {
            $connectionString .= " application_name=$this->applicationName";
        }

        return $connectionString;
    }

    public function dbName(): string
    {
        return $this->dbName;
    }

    public function endPoint(): IpEndPoint
    {
        return $this->endPoint;
    }

    public function userCredentials(): UserCredentials
    {
        return $this->userCredentials;
    }

    public function persistent(): bool
    {
        return $this->persistent;
    }

    public function sslmode(): ?string
    {
        return $this->sslmode;
    }

    public function sslrootcert(): ?string
    {
        return $this->sslrootcert;
    }

    public function sslcert(): ?string
    {
        return $this->sslcert;
    }

    public function sslkey(): ?string
    {
        return $this->sslkey;
    }

    public function sslcrl(): ?string
    {
        return $this->sslcrl;
    }

    public function applicationName(): ?string
    {
        return $this->applicationName;
    }
}
