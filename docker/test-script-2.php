<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\HttpEventStore\HttpEventStoreSyncConnection(
    new \Http\Client\Socket\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
    new \Http\Message\MessageFactory\DiactorosMessageFactory(),
    new \Http\Message\UriFactory\DiactorosUriFactory(),
    new \Prooph\HttpEventStore\ConnectionSettings(new \Prooph\EventStore\IpEndPoint('eventstore', 2113), false)
);

$result = $connection->createPersistentSubscription(
    'sasastream',
    'test',
    \Prooph\EventStore\Data\PersistentSubscriptionSettings::default(),
    new \Prooph\EventStore\Data\UserCredentials('admin', 'changeit')
);

\var_dump($result);

$result = $connection->updatePersistentSubscription(
    'sasastream',
    'test',
    new \Prooph\EventStore\Data\PersistentSubscriptionSettings(
        true,
        0,
        false,
        2000,
        500,
        10,
        20,
        1000,
        500,
        0,
        30000,
        10,
        \Prooph\EventStore\NamedConsumerStrategy::roundRobin()
    ),
    new \Prooph\EventStore\UserCredentials('admin', 'changeit')
);

\var_dump($result);
/*
$result = $connection->deletePersistentSubscription(
    'sasastream',
    'test',
    new \Prooph\EventStore\UserCredentials('admin', 'changeit')
);

var_dump($result);
*/
$result = $connection->getInformationForAllSubscriptions();

\var_dump($result);

$result = $connection->getInformationForSubscriptionsWithStream('sasastream');

\var_dump($result);

$result = $connection->getInformationForSubscription('sasastream', 'test');

\var_dump($result);
