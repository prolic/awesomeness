<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Delayed;
use Amp\Promise;
use Prooph\EventStore\Data\AllEventsSlice;
use Prooph\EventStore\Data\CatchUpSubscriptionSettings;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\EventStoreAsyncConnection;
use function Amp\call;

class EventStoreAllCatchUpSubscription extends EventStoreCatchUpSubscription
{
    /** @var Position */
    private $nextReadPosition;
    /** @var Position */
    private $lastProcessedPosition;

    /**
     * @internal
     *
     * @param EventStoreAsyncConnection $connection
     * @param Position|null $fromPositionExclusive
     * @param null|UserCredentials $userCredentials
     * @param callable(EventStoreCatchUpSubscription $subscription, ResolvedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStoreCatchUpSubscription $subscription): void $liveProcessingStarted
     * @param null|callable(EventStoreCatchUpSubscription $subscription, SubscriptionDropReason $reason, Throwable $exception):void $subscriptionDropped
     * @param CatchUpSubscriptionSettings $settings
     */
    public function __construct(
        EventStoreAsyncConnection $connection,
        // logger
        ?Position $fromPositionExclusive, // if null from the very beginning
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $liveProcessingStarted,
        ?callable $subscriptionDropped,
        CatchUpSubscriptionSettings $settings
    ) {
        parent::__construct(
            $connection,
            '',
            $userCredentials,
            $eventAppeared,
            $liveProcessingStarted,
            $subscriptionDropped,
            $settings
        );

        $this->lastProcessedPosition = $fromPositionExclusive ?? Position::end();
        $this->nextReadPosition = $fromPositionExclusive ?? Position::start();
    }

    public function lastProcessedPosition(): Position
    {
        return $this->lastProcessedPosition;
    }

    protected function readEventsTillAsync(
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition,
        ?int $lastEventNumber
    ): Promise {
        return $this->readEventsInternalAsync($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition);
    }

    private function readEventsInternalAsync(
        EventStoreAsyncConnection $connection,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials,
        ?int $lastCommitPosition
    ): Promise {
        return call(function () use ($connection, $resolveLinkTos, $userCredentials, $lastCommitPosition): \Generator {
            do {
                $slice = yield $connection->readAllEventsForwardAsync(
                    $this->nextReadPosition,
                    $this->readBatchSize,
                    $resolveLinkTos,
                    $userCredentials
                );

                $shouldStopOrDone = yield $this->readEventsCallbackAsync($slice, $lastCommitPosition);
            } while (! $shouldStopOrDone);
        });
    }

    /**
     * @return Promise<bool>
     */
    private function readEventsCallbackAsync(AllEventsSlice $slice, ?int $lastCommitPosition): Promise
    {
        return call(function () use ($slice, $lastCommitPosition): \Generator {
            $shouldStopOrDone = $this->shouldStop || yield $this->processEventsAsync($lastCommitPosition, $slice);

            if ($shouldStopOrDone && $this->verboseLogging) {
                /*
                Log.Debug(
                    "Catch-up Subscription {0} to {1}: finished reading events, nextReadPosition = {2}.",
                    SubscriptionName,
                    IsSubscribedToAll ? "<all>" : StreamId,
                    _nextReadPosition);
                */
            }

            return $shouldStopOrDone;
        });
    }

    /**
     * @return Promise<bool>
     */
    private function processEventsAsync(?int $lastCommitPosition, AllEventsSlice $slice): Promise
    {
        return call(function () use ($lastCommitPosition, $slice): \Generator {
            foreach ($slice->events() as $e) {
                if (null === $e->originalPosition()) {
                    throw new \Exception(\sprintf(
                        'Subscription %s event came up with no OriginalPosition',
                        $this->subscriptionName()
                    ));
                }

                yield $this->tryProcessAsync($e);
            }

            $this->nextReadPosition = $slice->nextPosition();

            $done = (null === $lastCommitPosition)
                ? $slice->isEndOfStream()
                : $slice->nextPosition()->greaterOrEquals(new Position($lastCommitPosition, $lastCommitPosition));

            if (! $done && $slice->isEndOfStream()) {
                yield new Delayed(1000); // we are waiting for server to flush its data
            }

            return $done;
        });
    }

    protected function tryProcessAsync(ResolvedEvent $e): Promise
    {
        return call(function () use ($e): \Generator {
            $processed = false;

            if ($e->originalPosition()->greater($this->lastProcessedPosition)) {
                try {
                    yield $this->eventAppeared($this, $e);
                } catch (\Throwable $ex) {
                    $this->dropSubscription(SubscriptionDropReason::eventHandlerException(), $ex);

                    throw $ex;
                }

                $this->lastProcessedPosition = $e->originalPosition();
                $processed = true;
            }
            /*
            if ($this->verboseLogging) {

            Log.Debug("Catch-up Subscription {0} to {1}: {2} event ({3}, {4}, {5} @ {6}).",
                SubscriptionName,
                IsSubscribedToAll ? "<all>" : StreamId,
                processed ? "processed" : "skipping",
                e.OriginalEvent.EventStreamId, e.OriginalEvent.EventNumber, e.OriginalEvent.EventType, e.OriginalPosition);
            }
            */
        });
    }
}
