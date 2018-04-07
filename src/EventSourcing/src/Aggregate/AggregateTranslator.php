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

interface AggregateTranslator
{
    public function extractAggregateVersion(object $aggregateRoot): int;

    public function extractAggregateId(object $aggregateRoot): string;

    public function reconstituteAggregateFromHistory(AggregateType $aggregateType, Iterator $historyEvents);

    /**
     * @return Message[]
     */
    public function extractPendingStreamEvents(object $aggregateRoot): array;

    public function replayStreamEvents(object $eventSourcedAggregateRoot, Iterator $events): void;
}
