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

use Iterator;
use Prooph\Common\Messaging\Message;

final class AggregateRootTranslator implements AggregateTranslator
{
    /**
     * @var AggregateRootDecorator
     */
    protected $aggregateRootDecorator;

    public function extractAggregateVersion(object $aggregateRoot): int
    {
        return $this->getAggregateRootDecorator()->extractAggregateVersion($aggregateRoot);
    }

    public function extractAggregateId(object $aggregateRoot): string
    {
        return $this->getAggregateRootDecorator()->extractAggregateId($aggregateRoot);
    }

    public function reconstituteAggregateFromHistory(AggregateType $aggregateType, Iterator $historyEvents): object
    {
        if (! $historyEvents->valid()) {
            throw new Exception\RuntimeException('History events are empty');
        }

        $firstEvent = $historyEvents->current();
        /* @var Message $firstEvent */
        $aggregateTypeString = $firstEvent->metadata()['_aggregate_type'] ?? '';

        $aggregateRootClass = $aggregateType->className($aggregateTypeString);

        return $this->getAggregateRootDecorator()
            ->fromHistory($aggregateRootClass, $historyEvents);
    }

    /**
     * @return Message[]
     */
    public function extractPendingStreamEvents(object $aggregateRoot): array
    {
        return $this->getAggregateRootDecorator()->extractRecordedEvents($aggregateRoot);
    }

    public function replayStreamEvents(object $aggregateRoot, Iterator $events): void
    {
        $this->getAggregateRootDecorator()->replayStreamEvents($aggregateRoot, $events);
    }

    public function getAggregateRootDecorator(): AggregateRootDecorator
    {
        if (null === $this->aggregateRootDecorator) {
            $this->aggregateRootDecorator = AggregateRootDecorator::newInstance();
        }

        return $this->aggregateRootDecorator;
    }

    public function setAggregateRootDecorator(AggregateRootDecorator $aggregateRootDecorator): void
    {
        $this->aggregateRootDecorator = $aggregateRootDecorator;
    }
}
