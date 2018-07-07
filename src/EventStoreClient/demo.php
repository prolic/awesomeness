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
    die;

    Loop::repeat(500, function () {
        echo '.';
    });

    Loop::repeat(5000, function () use ($connection) {
        $slice = yield $connection->readStreamEventsForwardAsync(
            'opium2-bar',
            10,
            4000,
            true
        );

        \var_dump(\get_class($slice));
    });
});
