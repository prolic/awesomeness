<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Promise;
use Google\Protobuf\Internal\GPBType;
use Google\Protobuf\Internal\RepeatedField;
use Prooph\EventStore\Messages\EventRecord;
use Prooph\EventStoreClient\Internal\Data\EventRecord as EventRecordData;
use Prooph\EventStoreClient\Internal\Data\PersistentSubscriptionAckEvents;
use Prooph\EventStoreClient\Internal\Data\PersistentSubscriptionNakEvents;
use Prooph\EventStoreClient\Internal\EventRecordConverter;
use Prooph\EventStoreClient\Internal\Message\MessageType;

class AcknowledgeableEventRecord extends EventRecord
{
    /**
     * Client unknown on action. Let server decide
     */
    public const NackActionUnknown = 0;
    /**
     * Park message, do not resend. Put on poison queue
     * Don't retry the message, park it until a request is sent to reply the parked messages
     */
    public const NackActionPark = 1;
    /**
     * Explicitly retry the message
     */
    public const NackActionRetry = 2;
    /**
     * Skip this message do not resend do not put in poison queue
     */
    public const NackActionSkip = 3;
    /**
     * Stop the subscription.
     */
    public const NackActionStop = 4;

    protected $binaryId;
    protected $correlationId;
    protected $group;
    protected $linkedEvent;
    private $writer;

    public function __construct(EventRecordData $event, string $correlationId, string $group, Writer $writer, EventRecordData $linkedEvent = null)
    {
        $this->binaryId = ($linkedEvent) ? $linkedEvent->getEventId() : $event->getEventId();

        $event = EventRecordConverter::convert($event);

        parent::__construct(
            $event->eventStreamId(),
            $event->eventNumber(),
            $event->eventId(),
            $event->eventType(),
            $event->isJson(),
            $event->data(),
            $event->metadata(),
            $event->created()
        );

        if ($linkedEvent) {
            $this->eventStreamId = $linkedEvent->getEventStreamId();
            $this->eventNumber = $linkedEvent->getEventNumber();
            $this->linkedEvent = EventRecordConverter::convert($linkedEvent);
        }

        $this->correlationId = $correlationId;
        $this->writer = $writer;
        $this->group = $group;
    }

    public function linkedEvent(): ?EventRecord
    {
        if ($this->linkedEvent) {
            return $this->linkedEvent;
        }
    }

    public function group(): string
    {
        return $this->group;
    }

    public function ack(): Promise
    {
        $ack = new PersistentSubscriptionAckEvents();
        $ack->setSubscriptionId($this->eventStreamId . '::' . $this->group);

        $events = new RepeatedField(GPBType::BYTES);
        $events[] = $this->binaryId;

        $ack->setProcessedEventIds($events);

        return $this->writer->composeAndWrite(
            MessageType::PersistentSubscriptionAckEvents,
            $ack,
            $this->correlationId
        );
    }

    public function nack(int $action = self::NackActionUnknown, string $msg = ''): Promise
    {
        $nack = new PersistentSubscriptionNakEvents();
        $nack->setSubscriptionId($this->eventStreamId . '::' . $this->group);
        $nack->setAction($action);
        $nack->setMessage($msg);

        $events = new RepeatedField(GPBType::BYTES);
        $nack->setProcessedEventIds($events);
        $events[] = $this->binaryId;

        return $this->writer->composeAndWrite(
            MessageType::PersistentSubscriptionNackEvents,
            $nack,
            $this->correlationId
        );
    }
}
