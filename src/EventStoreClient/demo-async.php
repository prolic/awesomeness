<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Prooph\EventStoreClient\Data\EventData;
use Prooph\EventStoreClient\Data\EventId;
use Prooph\EventStoreClient\Data\ExpectedVersion;
use Prooph\EventStoreClient\Data\Position;
use Prooph\EventStoreClient\Data\StreamMetadata;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\IpEndPoint;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    $connection = EventStoreConnection::createAsyncFromIpEndPoint(
        new IpEndPoint('localhost', 1113)
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
