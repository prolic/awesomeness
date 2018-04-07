<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\EventStoreHttpClient\EventStoreHttpConnection(
    new \Http\Client\Curl\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
    new \Http\Message\MessageFactory\DiactorosMessageFactory(),
    new \Http\Message\UriFactory\DiactorosUriFactory(),
    new \Prooph\EventStoreHttpClient\ConnectionSettings(new \Prooph\EventStoreClient\IpEndPoint('eventstore', 2113), false)
);

$task = $connection->appendToStreamAsync(
    'sasastream',
    \Prooph\EventStoreClient\ExpectedVersion::NoStream,
    [
        new \Prooph\EventStoreClient\EventData(
            \Prooph\EventStoreClient\EventId::generate(),
            'userCreated',
            true,
            json_encode(['user' => 'Sacha Prlc', 'email' => 'saschaprolic@googlemail.com']),
            ''
        ),
        new \Prooph\EventStoreClient\EventData(
            \Prooph\EventStoreClient\EventId::generate(),
            'userNameUpdated',
            true,
            json_encode(['user' => 'Sascha Prolic']),
            ''
        ),
    ]
);

var_dump($task->result());

$task = $connection->readStreamEventsForwardAsync(
    'sasastream',
    0,
    100,
    true
);

var_dump($task->result());

$task = $connection->setStreamMetadataAsync(
    'sasastream',
    \Prooph\EventStoreClient\ExpectedVersion::Any,
    new \Prooph\EventStoreClient\StreamMetadata(null, null, null, null, null, [
        'foo' => 'bar',
    ])
);

var_dump($task->result());

$task = $connection->getStreamMetadataAsync('sasastream');

var_dump($task->result());

$task = $connection->deleteStreamAsync('foo', false);

var_dump($task->result());
