<?php

declare(strict_types=1);

namespace ProophTest\PdoEventStore;

use Prooph\EventStore\EventStoreConnection;
use ProophTest\EventStore\EventStoreConnectionTest;

class PdoEventStoreConnectionTest extends EventStoreConnectionTest
{
    protected function getEventStoreConnection(): EventStoreConnection
    {
        return new \Prooph\PdoEventStore\PdoEventStoreConnection(
            new \Prooph\PdoEventStore\PostgresConnectionSettings(
                new \Prooph\EventStore\IpEndPoint(\getenv('PG_HOST'), (int) \getenv('PG_PORT')),
                \getenv('PG_DBNAME'),
                new \Prooph\EventStore\UserCredentials(\getenv('PG_USERNAME'), \getenv('PG_PASSWORD')),
                null,
                false
            )
        );
    }

    protected function cleanEventStore(): void
    {
        $connection = $this->getConnection();

        $queries = [
            'TRUNCATE streams',
            'TRUNCATE events',
        ];

        foreach ($queries as $query) {
            $stmt = $connection->prepare($query);
            $stmt->execute();
        }
    }

    protected function getStream(string $name): array
    {
        $connection = $this->getConnection();

        $stmt = $connection->prepare('SELECT * FROM streams WHERE stream_name = ? LIMIT 1');
        $stmt->execute([$name]);

        $stream = $stmt->fetch();
        if (false === $stream) {
            throw new \InvalidArgumentException(\sprintf(
                'Stream "%s" could not be found.',
                $name
            ));
        }

        $stmt = $connection->prepare('SELECT * FROM events WHERE stream_id IN (SELECT stream_id from streams WHERE stream_name = ?) ORDER BY event_number');
        $stmt->execute([$name]);

        $stream['events'] = $stmt->fetchAll();

        return $stream;
    }

    private function getConnection(): \PDO
    {
        $dsn = \sprintf(
            'pgsql:host=%s;port=%d;dbname=%s;user=%s;password=%s',
            \getenv('PG_HOST'),
            \getenv('PG_PORT'),
            \getenv('PG_DBNAME'),
            \getenv('PG_USERNAME'),
            \getenv('PG_PASSWORD')
        );

        $pdo = new \PDO($dsn);
        $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION);

        return $pdo;
    }
}
