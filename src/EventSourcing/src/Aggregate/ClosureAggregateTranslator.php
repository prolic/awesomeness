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
use RuntimeException;

final class ClosureAggregateTranslator implements AggregateTranslator
{
    protected $aggregateIdExtractor;
    protected $aggregateReconstructor;
    protected $pendingEventsExtractor;
    protected $replayStreamEvents;
    protected $versionExtractor;

    public function extractAggregateVersion(object $aggregateRoot): int
    {
        if (null === $this->versionExtractor) {
            $this->versionExtractor = function (): int {
                return $this->version;
            };
        }

        return $this->versionExtractor->call($aggregateRoot);
    }

    public function extractAggregateId(object $aggregateRoot): string
    {
        if (null === $this->aggregateIdExtractor) {
            $this->aggregateIdExtractor = function (): string {
                return $this->aggregateId();
            };
        }

        return $this->aggregateIdExtractor->call($aggregateRoot);
    }

    public function reconstituteAggregateFromHistory(AggregateType $aggregateType, Iterator $historyEvents): object
    {
        if (null === $this->aggregateReconstructor) {
            $this->aggregateReconstructor = function ($historyEvents) {
                return static::reconstituteFromHistory($historyEvents);
            };
        }

        $arClass = $aggregateType->toString();

        if (! class_exists($arClass)) {
            throw new RuntimeException(
                sprintf('Aggregate root class %s cannot be found', $arClass)
            );
        }

        return ($this->aggregateReconstructor->bindTo(null, $arClass))($historyEvents);
    }

    /**
     * @return Message[]
     */
    public function extractPendingStreamEvents(object $aggregateRoot): array
    {
        if (null === $this->pendingEventsExtractor) {
            $this->pendingEventsExtractor = function (): array {
                return $this->popRecordedEvents();
            };
        }

        return $this->pendingEventsExtractor->call($aggregateRoot);
    }

    public function replayStreamEvents(object $aggregateRoot, Iterator $events): void
    {
        if (null === $this->replayStreamEvents) {
            $this->replayStreamEvents = function ($events): void {
                $this->replay($events);
            };
        }
        $this->replayStreamEvents->call($aggregateRoot, $events);
    }
}
