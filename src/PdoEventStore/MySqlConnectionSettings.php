<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore;

use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\UserCredentials;

class MySqlConnectionSettings implements ConnectionSettings
{
    /** @var IpEndPoint */
    private $endPoint;
    /** @var string */
    private $dbName;
    /** @var UserCredentials */
    private $userCredentials;
    /** @var string|null */
    private $unixSocket;
    /** @var string|null */
    private $charset;

    public static function default(): PostgresConnectionSettings
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
        UserCredentials $userCredentials,
        string $unixSocket = null,
        string $charset = null
    ) {
        $this->endPoint = $endpoint;
        $this->dbName = $dbName;
        $this->userCredentials = $userCredentials;
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

    public function userCredentials(): UserCredentials
    {
        return $this->userCredentials;
    }

    public function unixSocket(): ?string
    {
        return $this->unixSocket;
    }

    public function charset(): ?string
    {
        return $this->charset;
    }
}
