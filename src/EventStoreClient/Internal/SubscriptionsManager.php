<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\Exception\ConnectionClosedException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\OperationTimedOutException;
use Prooph\EventStoreClient\Exception\RetriesLimitReachedException;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use SplQueue;

/** @internal  */
class SubscriptionsManager
{
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var SubscriptionItem[] */
    private $activeSubscriptions = [];
    /** @var SplQueue */
    private $waitingSubscriptions;
    /** @var SubscriptionItem[] */
    private $retryPendingSubscriptions = [];

    public function __construct(string $connectionName, ConnectionSettings $settings)
    {
        $this->connectionName = $connectionName;
        $this->settings = $settings;
        $this->waitingSubscriptions = new SplQueue();
    }

    public function getActiveSubscription(string $correlationId): ?SubscriptionItem
    {
        return $this->activeSubscriptions[$correlationId] ?? null;
    }

    public function cleanUp(): void
    {
        $connectionClosedException = ConnectionClosedException::withName($this->connectionName);

        foreach ($this->activeSubscriptions as $subscriptionItem) {
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        while (! $this->waitingSubscriptions->isEmpty()) {
            /** @var SubscriptionItem $subscriptionItem */
            $subscriptionItem = $this->waitingSubscriptions->dequeue();
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        foreach ($this->retryPendingSubscriptions as $subscriptionItem) {
            $subscriptionItem = $this->waitingSubscriptions->dequeue();
            $subscriptionItem->operation()->dropSubscription(
                SubscriptionDropReason::connectionClosed(),
                $connectionClosedException
            );
        }

        $this->activeSubscriptions = [];
        $this->retryPendingSubscriptions = [];
    }

    public function purgeSubscribedAndDroppedSubscriptions(string $connectionId): void
    {
        $subscriptionsToRemove = new SplQueue();

        foreach ($this->activeSubscriptions as $subscriptionItem) {
            if ($subscriptionItem->connectionId() !== $connectionId) {
                continue;
            }

            $subscriptionItem->operation()->connectionClosed();
            $subscriptionsToRemove->enqueue($subscriptionItem);
        }

        while (! $subscriptionsToRemove->isEmpty()) {
            /** @var SubscriptionItem $subscriptionItem */
            $subscriptionItem = $subscriptionsToRemove->dequeue();
            unset($this->activeSubscriptions[$subscriptionItem->correlationId()]);
        }
    }

    public function checkTimeoutsAndRetry(TcpPackageConnection $connection): void
    {
        $retrySubscriptions = new SplQueue();
        $removeSubscriptions = new SplQueue();

        foreach ($this->activeSubscriptions as $subscription) {
            if ($subscription->isSubscribed()) {
                continue;
            }

            if ($subscription->connectionId() !== $connection->connectionId()) {
                $this->retryPendingSubscriptions[] = $subscription;
            } elseif ($subscription->timeout() > 0
                && DateTimeUtil::utcNow()->format('U.u') - $subscription->lastUpdated()->format('U.u') > $this->settings->operationTimeout()
            ) {
                $err = \sprintf(
                    'EventStoreNodeConnection \'%s\': subscription never got confirmation from server',
                    $connection->connectionId()
                );

                // _settings.Log.Error(err);

                if ($this->settings->failOnNoServerResponse()) {
                    $subscription->operation()->dropSubscription(
                        SubscriptionDropReason::subscribingError(),
                        new OperationTimedOutException($err)
                    );
                    $removeSubscriptions->enqueue($subscription);
                } else {
                    $retrySubscriptions->enqueue($subscription);
                }
            }
        }

        while (! $retrySubscriptions->isEmpty()) {
            $this->scheduleSubscriptionRetry($retrySubscriptions->dequeue());
        }

        while (! $removeSubscriptions->isEmpty()) {
            $this->removeSubscription($removeSubscriptions->dequeue());
        }

        if (\count($this->retryPendingSubscriptions) > 0) {
            foreach ($this->retryPendingSubscriptions as $subscription) {
                $subscription->incRetryCount();
                $this->startSubscription($subscription, $connection);
            }

            $this->retryPendingSubscriptions = [];
        }

        while (! $this->waitingSubscriptions->isEmpty()) {
            $this->startSubscription($this->waitingSubscriptions->dequeue(), $connection);
        }
    }

    public function removeSubscription(SubscriptionItem $subscription): bool
    {
        $result = isset($this->activeSubscriptions[$subscription->correlationId()]);
        // LogDebug("RemoveSubscription {0}, result {1}.", subscription, res);
        unset($this->activeSubscriptions[$subscription->correlationId()]);

        return $result;
    }

    public function scheduleSubscriptionRetry(SubscriptionItem $subscription): void
    {
        if (! $this->removeSubscription($subscription)) {
            //LogDebug("RemoveSubscription failed when trying to retry {0}.", subscription);
            return;
        }

        if ($subscription->maxRetries() >= 0 && $subscription->retryCount() >= $subscription->maxRetries()) {
            //LogDebug("RETRIES LIMIT REACHED when trying to retry {0}.", subscription);
            $subscription->operation()->dropSubscription(
                SubscriptionDropReason::subscribingError(),
                RetriesLimitReachedException::with($subscription->retryCount())
            );

            return;
        }

        //LogDebug("retrying subscription {0}.", subscription);
        $this->retryPendingSubscriptions[] = $subscription;
    }

    public function enqueueSubscription(SubscriptionItem $subscriptionItem): void
    {
        $this->waitingSubscriptions->enqueue($subscriptionItem);
    }

    public function startSubscription(SubscriptionItem $subscription, TcpPackageConnection $connection): void
    {
        if ($subscription->isSubscribed()) {
            //LogDebug("StartSubscription REMOVING due to already subscribed {0}.", subscription);
            $this->removeSubscription($this);

            return;
        }

        $correlationId = CorrelationIdGenerator::generate();
        $subscription->setConnectionId($correlationId);
        $subscription->setConnectionId($connection->connectionId());
        $subscription->setLastUpdated(DateTimeUtil::utcNow());

        $this->activeSubscriptions[$correlationId] = $subscription;

        if (! $subscription->operation()->subscribe($correlationId, $connection)) {
            //LogDebug("StartSubscription REMOVING AS COULD NOT SUBSCRIBE {0}.", subscription);
            $this->removeSubscription($subscription);
        }
        //LogDebug("StartSubscription SUBSCRIBING {0}.", subscription);
    }

    private function logDebug(string $message): void
    {
        if ($this->settings->verboseLogging()) {
            $this->settings->logger()->debug(\sprintf(
                'EventStoreNodeConnection \'%s\': %s',
                $this->connectionName,
                $message
            ));
        }
    }
}
