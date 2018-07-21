<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\PdoEventStore\PdoEventStoreSyncSyncConnection(
    Prooph\PdoEventStore\PostgresConnectionSettings::default()
);

$connection->connect();
$writeResult = $connection->appendToStream(
    'sasastream',
    \Prooph\EventStore\ExpectedVersion::NoStream,
    [
        new \Prooph\EventStore\EventData(
            \Prooph\EventStore\EventId::generate(),
            'userCreated',
            true,
            \json_encode(['user' => 'Sacha Prlc', 'email' => 'saschaprolic@googlemail.com']),
            ''
        ),
        new \Prooph\EventStore\EventData(
            \Prooph\EventStore\EventId::generate(),
            'userNameUpdated',
            true,
            \json_encode(['user' => 'Sascha Prolic']),
            ''
        ),
    ]
);

$streamEventsSlice = $connection->readStreamEventsForward(
    'sasastream',
    0,
    100,
    false
);

\var_dump($streamEventsSlice);

$writeResult = $connection->setStreamMetadata(
    'sasastream',
    \Prooph\EventStore\ExpectedVersion::Any,
    new \Prooph\EventStore\StreamMetadata(null, null, null, null, null, [
        'foo' => 'bar',
    ])
);

\var_dump($writeResult);

$streamMetadataResult = $connection->getStreamMetadata('sasastream');

\var_dump($streamMetadataResult);

$connection->deleteStream('sasastream', false);
