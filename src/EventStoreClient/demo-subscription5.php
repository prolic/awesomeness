<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\Data\PersistentSubscriptionResolvedEvent;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\Messages\CreatePersistentSubscription;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;
use Prooph\EventStoreClient\Internal\EventStorePersistentSubscription;
use Prooph\EventStoreClient\Internal\StopWatch;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;

require __DIR__ . '/../../vendor/autoload.php';

Loop::run(function () {
    $connection = EventStoreConnection::createAsyncFromIpEndPoint(
        new IpEndPoint('localhost', 1113)
    );

    $connection->onConnected(function (): void {
        echo 'connected' . PHP_EOL;
    });

    $connection->onClosed(function (): void {
        echo 'connection closed' . PHP_EOL;
    });

    yield $connection->connectAsync();

    try {
        $result = yield $connection->deletePersistentSubscriptionAsync(
            'opium2-bar',
            'test-persistent-subscription',
            new UserCredentials('admin', 'changeit')
        );
        var_dump($result);
    } catch (InvalidOperationException $exception) {
        echo 'no such subscription exists (yet)' . PHP_EOL;
    }

    /** @var CreatePersistentSubscription $result */
    $result = yield $connection->createPersistentSubscriptionAsync(
        'opium2-bar',
        'test-persistent-subscription',
        PersistentSubscriptionSettings::default(),
        new UserCredentials('admin', 'changeit')
    );

    var_dump($result);

    $stopWatch = StopWatch::startNew();
    $i = 0;

    $subscription = $connection->connectToPersistentSubscription(
        'opium2-bar',
        'test-persistent-subscription',
        function (EventStorePersistentSubscription $subscription, PersistentSubscriptionResolvedEvent $event) use ($stopWatch, &$i): Promise {
            echo 'incoming event: ' . $event->originalEventNumber() . '@' . $event->originalStreamName() . PHP_EOL;
            echo 'data: ' . $event->originalEvent()->data() . PHP_EOL;
            echo 'no: ' . ++$i . ', elapsed: ' . $stopWatch->elapsed() . PHP_EOL;

            return new Success('tadataa');
        },
        function () {
            echo 'dropped' . PHP_EOL;
        },
        10,
        true,
        new UserCredentials('admin', 'changeit')
    );

    /** @var EventStorePersistentSubscription $subscription */
    $subscription = yield $subscription->start();

});
