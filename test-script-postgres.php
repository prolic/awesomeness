<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\PdoEventStore\PdoEventStoreConnection(
    Prooph\PdoEventStore\PostgresConnectionSettings::default()
);

$connection->connect();

for ($i = 0; $i < 10; $i++) {
    for ($j = 0; $j < 1000; $j++) {
        $data = [];
        $data[] = new \Prooph\EventStore\EventData(
            \Prooph\EventStore\EventId::generate(),
            'test-event',
            true,
            \json_encode(['data' => \Ramsey\Uuid\Uuid::uuid4()->toString()]),
            ''
        );
        $writeResult = $connection->appendToStream(
            'sasastream',
            \Prooph\EventStore\ExpectedVersion::Any,
            $data
        );
    }
}
