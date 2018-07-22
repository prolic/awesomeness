<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Data\EventData;
use Prooph\EventStoreClient\Data\EventId;
use Prooph\EventStoreClient\Data\ExpectedVersion;

require __DIR__ . '/../../vendor/autoload.php';

$connection = EventStoreConnectionBuilder::createFromIpEndPoint(
    new IpEndPoint('localhost', 1113)
);

$connection->onConnected(function (): void {
    echo 'connected' . PHP_EOL;
});

$connection->onClosed(function (): void {
    echo 'connection closed' . PHP_EOL;
});

$connection->connect();

$slice = $connection->readStreamEventsForward(
    'opium2-bar',
    100,
    20,
    true
);

\var_dump($slice);

$slice = $connection->readStreamEventsBackward(
    'opium2-bar',
    10,
    2,
    true
);

$event = $connection->readEvent('opium2-bar', 2, true);

\var_dump($event);

$m = $connection->getStreamMetadata('opium2-bar');

\var_dump($m);

$wr = $connection->appendToStream('opium2-bar', ExpectedVersion::Any, [
    new EventData(EventId::generate(), 'test-type', false, 'jfkhksdfhsds', 'meta'),
    new EventData(EventId::generate(), 'test-type2', false, 'kldjfls', 'meta'),
    new EventData(EventId::generate(), 'test-type3', false, 'aaa', 'meta'),
    new EventData(EventId::generate(), 'test-type4', false, 'bbb', 'meta'),
]);

\var_dump($wr);

$connection->close();
