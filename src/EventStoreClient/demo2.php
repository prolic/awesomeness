<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use function Amp\Promise\wait;

require __DIR__ . '/../../vendor/autoload.php';

$settings = ConnectionSettings::default();

$connection = new EventStore($settings);

wait($connection->connectAsync());

echo 'connected';

$slice = wait($connection->readStreamEventsForwardAsync(
    'opium2-bar',
    100,
    20,
    true
));

var_dump($slice);
