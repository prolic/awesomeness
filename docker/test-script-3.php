<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\EventStoreHttpClient\EventStoreHttpConnection(
    new \Http\Client\Curl\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
    new \Http\Message\MessageFactory\DiactorosMessageFactory(),
    new \Http\Message\UriFactory\DiactorosUriFactory(),
    new \Prooph\EventStoreHttpClient\ConnectionSettings(new \Prooph\EventStore\IpEndPoint('eventstore', 2113), false)
);

$subscription = $connection->connectToPersistentSubscription(
    'sasastream',
    'test',
    function (\Prooph\EventStore\EventStorePersistentSubscription $subscription, \Prooph\EventStore\RecordedEvent $event): void {
        echo $event->eventId()->toString() . PHP_EOL;
        echo $event->data() . PHP_EOL;
        echo '#########################' . PHP_EOL;
    }
);

$subscription->startSubscription();
