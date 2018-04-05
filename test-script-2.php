<?php

declare(strict_types=1);
require 'vendor/autoload.php';

$connection = new \Prooph\EventStoreHttpClient\EventStoreHttpConnection(
    new \Http\Client\Curl\Client(new \Http\Message\MessageFactory\DiactorosMessageFactory()),
    new \Http\Message\MessageFactory\DiactorosMessageFactory(),
    new \Http\Message\UriFactory\DiactorosUriFactory()
);

$task = $connection->createPersistentSubscription(
    'sasastream',
    'test',
    \Prooph\EventStore\PersistentSubscriptionSettings::default(),
    new \Prooph\EventStore\UserCredentials('admin', 'changeit')
);

var_dump($task->result());

$task = $connection->updatePersistentSubscription(
    'sasastream',
    'test',
    \Prooph\EventStore\PersistentSubscriptionSettings::default(),
    new \Prooph\EventStore\UserCredentials('admin', 'changeit')
);

var_dump($task->result());

$task = $connection->deletePersistentSubscription(
    'sasastream',
    'test',
    new \Prooph\EventStore\UserCredentials('admin', 'changeit')
);

var_dump($task->result());