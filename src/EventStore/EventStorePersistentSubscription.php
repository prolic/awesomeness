<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Amp\Loop;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\Internal\PersistentSubscriptionOperations;
use Throwable;

class EventStorePersistentSubscription
{
    /** @var PersistentSubscriptionOperations */
    private $operations;
    /** string */
    private $subscriptionId;
    /** @var string */
    private $streamId;
    /** @var callable(EventStorePersistentSubscription $subscription, RecordedEvent $event): Promise */
    private $eventAppeared;
    /** @var null|callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, Throwable $error): void */
    private $subscriptionDropped;
    /** @var bool */
    private $autoAck;
    /** @var int */
    private $bufferSize;
    /** @var bool */
    private $shouldStop;

    /**
     * EventStorePersistentSubscription constructor.
     * @param PersistentSubscriptionOperations $operations
     * @param string $subscriptionId
     * @param string $streamId
     * @param callable(EventStorePersistentSubscription $subscription, RecordedEvent $event): Promise $eventAppeared
     * @param null|callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, Throwable $error): void $subscriptionDropped
     * @param int $bufferSize
     * @param bool $autoAck
     */
    public function __construct(
        PersistentSubscriptionOperations $operations,
        string $subscriptionId,
        string $streamId,
        callable $eventAppeared,
        ?callable $subscriptionDropped,
        int $bufferSize,
        bool $autoAck
    ) {
        $this->operations = $operations;
        $this->subscriptionId = $subscriptionId;
        $this->streamId = $streamId;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped ?? function (): void {
        };
        $this->bufferSize = $bufferSize;
        $this->autoAck = $autoAck;
    }

    public function startSubscription(): void
    {
        Loop::defer(function (): \Generator {
            $this->shouldStop = false;
            $eventAppeared = $this->eventAppeared;

            $subscriptionDropped = $this->subscriptionDropped;

            while (! $this->shouldStop) {
                $events = $this->operations->readFromSubscription($this->bufferSize);

                if (empty($events)) {
                    \usleep(100000);
                    continue;
                }

                if ($this->autoAck) {
                    $toAck = [];
                    $toNack = [];
                }

                $error = false;

                foreach ($events as $event) {
                    try {
                        yield $eventAppeared($this, $event);

                        if ($this->autoAck) {
                            $toAck[] = $event->eventId();
                        }
                    } catch (Throwable $ex) {
                        $subscriptionDropped($this, SubscriptionDropReason::eventHandlerException(), $ex);

                        if ($this->autoAck) {
                            $toNack[] = $event->eventId();
                        }

                        $error = true;
                        break;
                    }
                }

                if ($this->autoAck && ! empty($toAck)) {
                    $this->operations->acknowledge($toAck);
                }

                if ($error && $this->autoAck) {
                    $this->operations->fail($toNack, PersistentSubscriptionNakEventAction::retry());
                    $this->shouldStop = true;
                }
            }
        });
    }

    public function acknowledge(EventId $eventId): void
    {
        $this->operations->acknowledge([$eventId]);
    }

    /**
     * @param EventId[] $events
     */
    public function acknowledgeMultiple(array $eventIds): void
    {
        if (\count($eventIds) > 2000) {
            throw new \RuntimeException('Limited to ack 2000 events at a time');
        }

        $this->operations->acknowledge($eventIds);
    }

    public function fail(EventId $eventId, PersistentSubscriptionNakEventAction $action): void
    {
        $this->operations->fail([$eventId], $action);
    }

    /**
     * @param EventId[] $events
     * @param PersistentSubscriptionNakEventAction $action
     */
    public function failMultiple(array $eventIds, PersistentSubscriptionNakEventAction $action): void
    {
        if (\count($eventIds) > 2000) {
            throw new \RuntimeException('Limited to nack 2000 events at a time');
        }

        $this->operations->fail($eventIds, $action);
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
