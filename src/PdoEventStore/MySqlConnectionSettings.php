<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\IpEndPoint;

class MySqlConnectionSettings implements ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $dbName;
    /** @var UserCredentials */
    private $pdoUserCredentials;
    /** @var string|null */
    private $unixSocket;
    /** @var string|null */
    private $charset;
    /** @var UserCredentials|null */
    private $defaultUserCredentials;

    public static function default(): MySqlConnectionSettings
    {
        return new self(
            new IpEndPoint('localhost', 3306),
            'event_store',
            new UserCredentials('root', '')
        );
    }

    public function __construct(
        IpEndPoint $endpoint,
        string $dbName,
        UserCredentials $pdoUserCredentials,
        UserCredentials $defaultUserCredentials = null,
        string $unixSocket = null,
        string $charset = null
    ) {
        $this->endPoint = $endpoint;
        $this->dbName = $dbName;
        $this->pdoUserCredentials = $pdoUserCredentials;
        $this->defaultUserCredentials = $defaultUserCredentials;
        $this->unixSocket = $unixSocket;
        $this->charset = $charset;
    }

    public function connectionString(): string
    {
        $dsn = "mysql:dbname=$this->dbName;host={$this->endPoint->host()};port={$this->endPoint->port()};";

        if (null !== $this->unixSocket) {
            $dsn .= "unix_socket=$this->unixSocket;";
        }

        if (null !== $this->charset) {
            $dsn .= "charset=$this->charset;";
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

    public function unixSocket(): ?string
    {
        return $this->unixSocket;
    }

    public function charset(): ?string
    {
        return $this->charset;
    }

    public function defaultUserCredentials(): ?UserCredentials
    {
        return $this->defaultUserCredentials;
    }
}
