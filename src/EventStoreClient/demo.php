<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Prooph\EventStoreClient\Internal\StaticEndPointDiscoverer;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    $settings = ConnectionSettings::default();

    $endPointDiscoverer = new StaticEndPointDiscoverer($settings->endPoint(), $settings->useSslConnection());
    $connection = new EventStoreAsyncConnection($settings, $endPointDiscoverer, 'test');

    yield $connection->connectAsync();

    echo 'connected';

    $slice = yield $connection->readStreamEventsForwardAsync(
        'opium2-bar',
        10,
        2,
        true
    );

    \var_dump($slice);

    $connection->close();
    die;
    $slice = yield $connection->readStreamEventsBackwardAsync(
        'opium2-bar',
        10,
        2,
        true
    );

    \var_dump($slice);
    die;
    $event = yield $connection->readEventAsync('opium2-bar', 2, true);

    \var_dump($event);

    $m = yield $connection->getStreamMetadataAsync('opium2-bar');

    \var_dump($m);

    $connection->close();
});
