<?php
/**
 * This file is part of the prooph/event-sourcing.
 * (c) 2014-2018 prooph software GmbH <contact@prooph.de>
 * (c) 2015-2018 Sascha-Oliver Prolic <saschaprolic@googlemail.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace Prooph\EventSourcing\Aggregate;

use ArrayIterator;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventStoreAsyncConnection;
use Prooph\EventStore\ExpectedVersion;
use Prooph\EventStore\SliceReadStatus;
use Prooph\MessageTransformer;

class AggregateRepository
{
    /** @var EventStoreAsyncConnection */
    protected $eventStoreConnection;
    /** @var AggregateTranslator */
    protected $aggregateTranslator;
    /** @var AggregateType */
    protected $aggregateType;
    /** @var MessageTransformer */
    protected $transformer;
    /** @var array */
    protected $identityMap = [];
    /** @var string */
    protected $category;
    /** @var bool */
    protected $optimisticConcurrency;

    public function __construct(
        EventStoreAsyncConnection $eventStoreConnection,
        AggregateType $aggregateType,
        AggregateTranslator $aggregateTranslator,
        MessageTransformer $transformer,
        string $category,
        bool $optimisticConcurrency = true
    ) {
        $this->eventStoreConnection = $eventStoreConnection;
        $this->aggregateType = $aggregateType;
        $this->aggregateTranslator = $aggregateTranslator;
        $this->transformer = $transformer;
        $this->category = $category;
        $this->optimisticConcurrency = $optimisticConcurrency;
    }

    public function saveAggregateRoot(object $eventSourcedAggregateRoot): void
    {
        $this->aggregateType->assert($eventSourcedAggregateRoot);

        $domainEvents = $this->aggregateTranslator->extractPendingStreamEvents($eventSourcedAggregateRoot);
        $aggregateId = $this->aggregateTranslator->extractAggregateId($eventSourcedAggregateRoot);
        $stream = $this->category . '-' . $aggregateId;

        $eventData = [];

        $firstEvent = reset($domainEvents);

        if (false === $firstEvent) {
            return;
        }

        foreach ($domainEvents as $event) {
            $eventData[] = $this->transformer->toEventData($this->enrichEventMetadata($event, $aggregateId));
        }

        if ($this->isFirstEvent($firstEvent)) {
            $expectedVersion = ExpectedVersion::NoStream;
        } elseif ($this->optimisticConcurrency) {
            $expectedVersion = $this->aggregateTranslator->extractAggregateVersion($eventSourcedAggregateRoot) - count($eventData);
        } else {
            $expectedVersion = ExpectedVersion::Any;
        }

        $task = $this->eventStoreConnection->appendToStreamAsync(
            $stream,
            $expectedVersion,
            $eventData
        );

        $task->result();

        if (isset($this->identityMap[$aggregateId])) {
            unset($this->identityMap[$aggregateId]);
        }
    }

    /**
     * Returns null if no stream events can be found for aggregate root otherwise the reconstituted aggregate root
     */
    public function getAggregateRoot(string $aggregateId): ?object
    {
        if (isset($this->identityMap[$aggregateId])) {
            return $this->identityMap[$aggregateId];
        }

        $stream = $this->category . '-' . $aggregateId;

        $iterator = new ArrayIterator();

        $task = $this->eventStoreConnection->readStreamEventsForwardAsync($stream, 0, 100, true);

        do {
            $result = $task->result();

            if (! $result->status()->equals(SliceReadStatus::success())) {
                return null;
            }

            if (! $result->isEndOfStream()) {
                $task = $this->eventStoreConnection->readStreamEventsForwardAsync(
                    $stream,
                    $result->lastEventNumber() + 1,
                    100,
                    true
                );
            }

            foreach ($result->events() as $event) {
                $iterator->append($this->transformer->toMessage($event));
            }

            if (isset($eventSourcedAggregateRoot)) {
                $this->aggregateTranslator->replayStreamEvents($eventSourcedAggregateRoot, $iterator);
            } else {
                $eventSourcedAggregateRoot = $this->aggregateTranslator->reconstituteAggregateFromHistory(
                    $this->aggregateType,
                    $iterator
                );
            }
        } while (! $result->isEndOfStream());

        //Cache aggregate root in the identity map but without pending events
        $this->identityMap[$aggregateId] = $eventSourcedAggregateRoot;

        return $eventSourcedAggregateRoot;
    }

    /**
     * Empties the identity map. Use this if you load thousands of aggregates to free memory e.g. modulo 500.
     */
    public function clearIdentityMap(): void
    {
        $this->identityMap = [];
    }

    protected function isFirstEvent(Message $message): bool
    {
        return 0 === $message->metadata()['_aggregate_version'];
    }

    /**
     * Add aggregate_id and aggregate_type as metadata to $domainEvent
     * Override this method in an extending repository to add more or different metadata.
     */
    protected function enrichEventMetadata(object $eventSourcedAggregateRoot, Message $domainEvent, string $aggregateId): Message
    {
        $domainEvent = $domainEvent->withAddedMetadata('_aggregate_id', $aggregateId);
        $domainEvent = $domainEvent->withAddedMetadata('_aggregate_type', $this->aggregateType->typeFromAggregate($eventSourcedAggregateRoot));
        $domainEvent = $domainEvent->withAddedMetadata('effectiveTime', $domainEvent->createdAt()->format('Y-m-d H:i:s.u'));

        return $domainEvent;
    }
}
