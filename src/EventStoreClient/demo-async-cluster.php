<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\ExpectedVersion;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\StreamMetadata;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStoreClient\IpEndPoint;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    $builder = new ConnectionSettingsBuilder();
    $builder->setGossipSeedEndPoints([
        new IpEndPoint('localhost', 2113),
        new IpEndPoint('localhost', 2123),
        new IpEndPoint('localhost', 2133),
    ]);

    $connection = EventStoreConnection::createAsyncFromSettings(
        null,
        $builder->build(),
        'cluster-connection'
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    $slice = yield $connection->readStreamEventsForwardAsync(
        'opium2-bar',
        10,
        2,
        true
    );

    \var_dump($slice);

    $slice = yield $connection->readStreamEventsBackwardAsync(
        'opium2-bar',
        10,
        2,
        true
    );

    \var_dump($slice);

    $event = yield $connection->readEventAsync('opium2-bar', 2, true);

    \var_dump($event);

    $m = yield $connection->getStreamMetadataAsync('opium2-bar');

    \var_dump($m);

    $r = yield $connection->setStreamMetadataAsync('opium2-bar', ExpectedVersion::Any, new StreamMetadata(
        null, null, null, null, null, [
            'foo' => 'bar',
        ]
    ));

    \var_dump($r);

    $m = yield $connection->getStreamMetadataAsync('opium2-bar');

    \var_dump($m);

    $wr = yield $connection->appendToStreamAsync('opium2-bar', ExpectedVersion::Any, [
        new EventData(EventId::generate(), 'test-type', false, 'jfkhksdfhsds', 'meta'),
        new EventData(EventId::generate(), 'test-type2', false, 'kldjfls', 'meta'),
        new EventData(EventId::generate(), 'test-type3', false, 'aaa', 'meta'),
        new EventData(EventId::generate(), 'test-type4', false, 'bbb', 'meta'),
    ]);

    \var_dump($wr);

    $ae = yield $connection->readAllEventsForwardAsync(Position::start(), 2, false, new UserCredentials(
        'admin',
        'changeit'
    ));

    \var_dump($ae);

    $aeb = yield $connection->readAllEventsBackwardAsync(Position::end(), 2, false, new UserCredentials(
        'admin',
        'changeit'
    ));

    \var_dump($aeb);

    $connection->close();
});
