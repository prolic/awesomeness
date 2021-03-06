<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\HttpEventStore\HttpEventStoreSyncConnection(
    new \Http\Client\Socket\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
    new \Http\Message\MessageFactory\DiactorosMessageFactory(),
    new \Http\Message\UriFactory\DiactorosUriFactory()
);

$subscription = $connection->connectToPersistentSubscription(
    'sasastream',
    'test',
    function (\Prooph\EventStore\EventStorePersistentSubscription $subscription, \Prooph\EventStore\ResolvedEvent $event): void {
        echo $event->originalEvent()->eventId()->toString() . PHP_EOL;
        echo $event->originalEvent()->data() . PHP_EOL;
        echo '#########################' . PHP_EOL;
    }
);

$subscription->startSubscription();
