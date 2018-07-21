<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Internal\Event\ClientConnectionEventArgs;
use Prooph\EventStore\Internal\Event\ListenerHandler;
use Prooph\EventStoreClient\Data\CatchUpSubscriptionSettings;
use Prooph\EventStoreClient\Data\ResolvedEvent;
use Prooph\EventStoreClient\Data\SubscriptionDropReason;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\Exception\TimeoutException;
use Psr\Log\LoggerInterface as Logger;
use SplQueue;
use function Amp\call;

/** @internal  */
abstract class EventStoreCatchUpSubscription
{
    /** @var ResolvedEvent */
    private static $dropSubscriptionEvent;

    /** @var bool */
    private $isSubscribedToAll;
    /** @var string */
    private $streamId;
    /** @var string */
    private $subscriptionName;

    /** @var Logger */
    protected $logger;

    /** @var EventStoreAsyncConnection */
    private $connection;
    /** @var bool */
    private $resolveLinkTos;
    /** @var UserCredentials|null */
    private $userCredentials;

    /** @var int */
    protected $readBatchSize;
    /** @var int */
    protected $maxPushQueueSize;

    /** @var callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise */
    protected $eventAppeared;
    /** @var null|callable(EventStoreCatchUpSubscription $subscription): void */
    private $liveProcessingStarted;
    /** @var null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Throwable $exception): void */
    private $subscriptionDropped;

    /** @var bool */
    protected $verboseLogging;

    /** @var SplQueue<ResolvedEvent> */
    private $liveQueue;
    /** @var EventStoreSubscription */
    private $subscription;
    /** @var DropData|null */
    private $dropData;
    /** @var bool */
    private $allowProcessing;
    /** @var bool */
    private $isProcessing;
    /** @var bool */
    protected $shouldStop;
    /** @var bool */
    private $isDropped;
    /** @var bool */
    private $stopped;

    /** @var ListenerHandler */
    private $connectListener;

    /**
     * @param EventStoreAsyncConnection $connection
     * @param string $streamId
     * @param null|UserCredentials $userCredentials
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Throwable $exception):void $subscriptionDropped
     * @param CatchUpSubscriptionSettings $settings
     */
    public function __construct(
        EventStoreAsyncConnection $connection,
        //ILogger log,
        string $streamId,
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $liveProcessingStarted,
        ?callable $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        if (null === self::$dropSubscriptionEvent) {
            self::$dropSubscriptionEvent = new ResolvedEvent(null, null, null);
        }

        $this->connection = $connection;
        $this->isSubscribedToAll = empty($streamId);
        $this->streamId = $streamId;
        $this->userCredentials = $userCredentials;
        $this->eventAppeared = $eventAppeared;
        $this->liveProcessingStarted = $liveProcessingStarted ?? function (): void {
        };
        $this->subscriptionDropped = $subscriptionDropped ?? function (): void {
        };
        $this->resolveLinkTos = $settings->resolveLinkTos();
        $this->readBatchSize = $settings->readBatchSize();
        $this->maxPushQueueSize = $settings->maxLiveQueueSize();
        //$this->verboseLogging = $settings->verboseLogging(); // @todo
        $this->subscriptionName = $settings->subscriptionName() ?? '';
        $this->connectListener = function (): void {
        };
    }

    public function isSubscribedToAll(): bool
    {
        return $this->isSubscribedToAll;
    }

    public function streamId(): string
    {
        return $this->streamId;
    }

    public function subscriptionName(): string
    {
        return $this->subscriptionName;
    }

    abstract protected function readEventsTillAsync(
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise;

    abstract protected function tryProcessAsync(ResolvedEvent $e): Promise;

    /** @internal */
    public function startAsync(): Promise
    {
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: starting...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);
        return $this->runSubscriptionAsync();
    }

    public function stopWithTimeout(int $timeout): void
    {
        $this->stop();
        //if (Verbose) Log.Debug("Waiting on subscription {0} to stop", SubscriptionName);
        Loop::delay($timeout, function (): void {
            if (! $this->stopped) {
                throw new TimeoutException('Could not stop in time');
            }
        });
    }

    public function stop(): void
    {
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: requesting stop...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: unhooking from connection.Connected.", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

        $this->connection->detach($this->connectListener);
        $this->shouldStop = true;
        $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::userInitiated(), null);
    }

    private function onReconnect(ClientConnectionEventArgs $clientConnectionEventArgs): void
    {
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: recovering after reconnection.", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: unhooking from connection.Connected.", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

        $this->connection->detach($this->connectListener);
        Loop::defer(function (): Generator {
            yield $this->runSubscriptionAsync();
        });
    }

    private function runSubscriptionAsync(): Promise
    {
        return $this->loadHistoricalEventsAsync();
    }

    private function loadHistoricalEventsAsync(): Promise
    {
        //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: running...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);
        $this->stopped = false;
        $this->allowProcessing = false;

        return call(function (): Generator {
            if (! $this->shouldStop) {
                //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: pulling events...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

                try {
                    yield $this->readEventsTillAsync($this->connection, $this->resolveLinkTos, $this->userCredentials, null, null);
                    yield $this->subscribeToStreamAsync();
                } catch (\Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::catchUpError(), $ex);
                    throw $ex;
                }
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }
        });
    }

    private function subscribeToStreamAsync(): Promise
    {
        return call(function (): Generator {
            if (! $this->shouldStop) {
                //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: subscribing...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

                $subscription = empty($this->streamId) ?
                    yield $this->connection->subscribeToAllAsync(
                        $this->resolveLinkTos,
                        function (EventStoreSubscription $subscription, ResolvedEvent $e): Promise {
                            return $this->enqueuePushedEvent($subscription, $e);
                        },
                        function (EventStoreSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void {
                            $this->serverSubscriptionDropped($subscription, $reason, $exception);
                        },
                        $this->userCredentials
                    )
                    : yield $this->connection->subscribeToStreamAsync(
                        $this->streamId,
                        $this->resolveLinkTos,
                        function (EventStoreSubscription $subscription, ResolvedEvent $e): Promise {
                            return $this->enqueuePushedEvent($subscription, $e);
                        },
                        function (EventStoreSubscription $subscription, SubscriptionDropReason $reason, \Throwable $exception): void {
                            $this->serverSubscriptionDropped($subscription, $reason, $exception);
                        },
                        $this->userCredentials
                    );

                $this->subscription = $subscription;
                yield $this->readMissedHistoricEventsAsync();
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }
        });
    }

    private function readMissedHistoricEventsAsync(): Promise
    {
        return call(function (): Generator {
            if (! $this->shouldStop) {
                //if (Verbose) Log.Debug("Catch-up Subscription {0} to {1}: pulling events (if left)...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

                yield $this->readEventsTillAsync(
                    $this->connection,
                    $this->resolveLinkTos,
                    $this->userCredentials,
                    $this->subscription->lastCommitPosition(),
                    $this->subscription->lastEventNumber()
                );
                $this->startLiveProcessing();
            } else {
                $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);
            }
        });
    }

    private function startLiveProcessing(): void
    {
        if ($this->shouldStop) {
            $this->dropSubscription(SubscriptionDropReason::userInitiated(), null);

            return;
        }

        //if (Verbose) Log . Debug("Catch-up Subscription {0} to {1}: processing live events...", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);

        ($this->liveProcessingStarted)($this);

        //if (Verbose) Log . Debug("Catch-up Subscription {0} to {1}: hooking to connection.Connected", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId);
        $this->connectListener = $this->connection->onConnected(function (ClientConnectionEventArgs $args): void {
            $this->onReconnect($args);
        });

        $this->allowProcessing = true;
    }

    private function enqueuePushedEvent(EventStoreSubscription $subscription, ResolvedEvent $e): Promise
    {
        /*
        if (Verbose) {
            Log.Debug("Catch-up Subscription {0} to {1}: event appeared ({2}, {3}, {4} @ {5}).",
            SubscriptionName,
            IsSubscribedToAll ? "<all>" : StreamId,
            e.OriginalStreamId, e.OriginalEventNumber, e.OriginalEvent.EventType, e.OriginalPosition);
        }
        */

        if ($this->liveQueue->count() >= $this->maxPushQueueSize) {
            $this->enqueueSubscriptionDropNotification(SubscriptionDropReason::processingQueueOverflow(), null);
            $subscription->unsubscribe();

            return new Success();
        }

        $this->liveQueue->enqueue($e);

        if ($this->allowProcessing) {
            $this->ensureProcessingPushQueue();
        }

        return new Success();
    }

    private function serverSubscriptionDropped(
        EventStoreSubscription $subscription,
        SubscriptionDropReason $reason,
        \Throwable $exception): void
    {
        $this->enqueueSubscriptionDropNotification($reason, $exception);
    }

    private function enqueueSubscriptionDropNotification(SubscriptionDropReason $reason, \Throwable $error): void
    {
        // if drop data was already set -- no need to enqueue drop again, somebody did that already
        $dropData = new DropData($reason, $error);

        if ($this->dropData === $dropData) {
            $this->liveQueue->enqueue(self::$dropSubscriptionEvent);

            $this->dropData = null;
        }

        if ($this->allowProcessing) {
            $this->ensureProcessingPushQueue();
        }
    }

    private function ensureProcessingPushQueue(): void
    {
        if ($this->isProcessing) {
            $this->isProcessing = false;

            Loop::defer(function (): Generator {
                yield $this->processLiveQueueAsync();
            });
        }
    }

    private function processLiveQueueAsync(): Promise
    {
        return call(function (): Generator {
            $this->isProcessing = true;
            do {
                /** @var ResolvedEvent $e */
                while (! $this->liveQueue->isEmpty()) {
                    $e = $this->liveQueue->dequeue();

                    if ($e === self::$dropSubscriptionEvent) {
                        $this->dropData = $this->dropData ?? new DropData(SubscriptionDropReason::unknown(), new \Exception('Drop reason not specified'));
                        $this->dropSubscription($this->dropData->reason(), $this->dropData->error());

                        if ($this->isProcessing) {
                            $this->isProcessing = false;
                        }
                    }

                    return null;
                }

                try {
                    yield $this->tryProcessAsync($e);
                } catch (\Throwable $ex) {
                    //Log.Debug("Catch-up Subscription {0} to {1} Exception occurred in subscription {1}", SubscriptionName, IsSubscribedToAll ? "<all>" : StreamId, exc);
                    $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                    return null;
                }

                if ($this->isProcessing) {
                    $this->isProcessing = false;
                }
            } while ($this->liveQueue->count() > 0 && $this->isProcessing);

            $this->isProcessing = false;
        });
    }

    public function dropSubscription(SubscriptionDropReason $reason, \Throwable $error): void
    {
        if (! $this->isDropped) {
            $this->isDropped = true;

            /*
             * if (Verbose)
            Log.Debug("Catch-up Subscription {0} to {1}: dropping subscription, reason: {2} {3}.",
            SubscriptionName,
            IsSubscribedToAll ? "<all>" : StreamId,
            reason, error == null ? string.Empty : error.ToString());
             */

            if ($this->subscription) {
                $this->subscription->unsubscribe();
            }

            ($this->subscriptionDropped)($this, $reason, $error);
            $this->stopped = true;
        }
    }
}
