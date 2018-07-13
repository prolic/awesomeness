<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

require __DIR__ . '/../../vendor/autoload.php';

$settings = ConnectionSettings::default();

$connection = new EventStoreConnection(new EventStoreAsyncConnection($settings, 'test'));

$connection->connect();

echo 'connected';

$slice = $connection->readStreamEventsForward(
    'opium2-bar',
    100,
    20,
    true
);

\var_dump($slice);
