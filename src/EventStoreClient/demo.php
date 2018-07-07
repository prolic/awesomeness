<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    $settings = ConnectionSettings::default();

    $connection = new EventStoreAsyncConnection($settings);

    yield $connection->connectAsync();

    echo 'connected';

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

    //$event = yield $connection->readEventAsync('opium2-bar', 2, true);

    //\var_dump($event);

    $m = yield $connection->getStreamMetadataAsync('opium2-bar');

    \var_dump($m);
});
