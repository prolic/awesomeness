<?php

declare(strict_types=1);

namespace Prooph\EventStore;

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
    /** @var callable(EventStorePersistentSubscription $subscription, RecordedEvent $event) */
    private $eventAppeared;
    /** @var callable(EventStorePersistentSubscription $subscription, SubscriptionDropReason $reason, Throwable $error) */
    private $subscriptionDropped;
    /** @var bool */
    private $autoAck;
    /** @var int */
    private $bufferSize;
    /** @var bool */
    private $shouldStop;

    public function __construct(
        PersistentSubscriptionOperations $operations,
        string $subscriptionId,
        string $streamId,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize,
        bool $autoAck
    ) {
        $this->operations = $operations;
        $this->subscriptionId = $subscriptionId;
        $this->streamId = $streamId;
        $this->eventAppeared = $eventAppeared;
        $this->subscriptionDropped = $subscriptionDropped;
        $this->bufferSize = $bufferSize;
        $this->autoAck = $autoAck;
    }

    public function startSubscription(): void
    {
        $this->shouldStop = false;
        $eventAppeared = $this->eventAppeared;

        if ($this->subscriptionDropped) {
            $subscriptionDropped = $this->subscriptionDropped;
        }

        while (! $this->shouldStop) {
            $events = $this->operations->readFromSubscription($this->bufferSize);

            if (empty($events)) {
                usleep(100000);
                continue;
            }

            if ($this->autoAck) {
                $toAck = [];
                $toNack = [];
            }

            $error = false;

            foreach ($events as $event) {
                try {
                    $eventAppeared($this, $event);

                    if ($this->autoAck) {
                        $toAck[] = $event->eventId();
                    }
                } catch (Throwable $ex) {
                    if (isset($subscriptionDropped)) {
                        $subscriptionDropped($this, SubscriptionDropReason::eventHandlerException(), $ex);
                    }

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
        if (count($eventIds) > 2000) {
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
        if (count($eventIds) > 2000) {
            throw new \RuntimeException('Limited to nack 2000 events at a time');
        }

        $this->operations->fail($eventIds, $action);
    }

    public function stop(): void
    {
        $this->shouldStop = true;
    }
}
