<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Prooph\EventStore\Data\CatchUpSubscriptionSettings;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStoreClient\Internal\EventStoreCatchUpSubscription;

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

    $connection->subscribeToStreamFrom(
        'opium2-bar',
        null,
        CatchUpSubscriptionSettings::default(),
        function (EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise {
            echo 'incoming event: ' . $event->originalEventNumber() . '@' . $event->originalStreamName() . PHP_EOL;
            echo 'data: ' . $event->originalEvent()->data() . PHP_EOL;

            return new Success();
        },
        function (EventStoreCatchUpSubscription $subscription): void {
            echo 'liveProcessingStarted on ' . $subscription->streamId() . PHP_EOL;
        },
        function (EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void {
            echo 'dropped with reason: ' . $reason->name() . PHP_EOL;
            echo 'ex: ' . $exception->getMessage() . PHP_EOL;
        }
    );
});
