<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;

class PostgresConnectionSettings implements ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $dbName;
    /** @var UserCredentials */
    private $pdoUserCredentials;
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
    /** @var UserCredentials|null */
    private $defaultUserCredentials;

    public static function default(): PostgresConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 5432),
            'event_store',
            new UserCredentials('postgres', ''),
            null,
            false
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        string $dbName,
        UserCredentials $pdoUserCredentials,
        UserCredentials $defaultUserCredentials = null,
        bool $persistent = false,
        string $sslmode = null,
        string $sslrootcert = null,
        string $sslcert = null,
        string $sslkey = null,
        string $sslcrl = null,
        string $applicationName = null
    ) {
        $this->endPoint = $endpoint;
        $this->dbName = $dbName;
        $this->pdoUserCredentials = $pdoUserCredentials;
        $this->defaultUserCredentials = $defaultUserCredentials;
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
        $dsn = "pgsql:dbname=$this->dbName;host={$this->endPoint->host()};port={$this->endPoint->port()};";

        if (null !== $this->sslmode) {
            $dsn .= "sslmode=$this->sslmode;";
        }

        if (null !== $this->sslcert) {
            $dsn .= "sslrootcert=$this->sslrootcert;";
        }

        if (null !== $this->sslcert) {
            $dsn .= "sslcert=$this->sslcert;";
        }

        if (null !== $this->sslkey) {
            $dsn .= "sslkey=$this->sslkey;";
        }

        if (null !== $this->sslcrl) {
            $dsn .= "sslcrl=$this->sslcrl;";
        }

        if (null !== $this->applicationName) {
            $dsn .= "application_name=$this->applicationName;";
        }

        return $dsn;
    }

    public function dbName(): string
    {
        return $this->dbName;
    }

    public function endPoint(): IpEndPoint
    {
        return $this->endPoint;
    }

    public function pdoUserCredentials(): UserCredentials
    {
        return $this->pdoUserCredentials;
    }

    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
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
