<?php

declare(strict_types=1);

namespace Prooph;

use Prooph\Common\Messaging\DomainMessage;
use Prooph\Common\Messaging\Message;
use Prooph\EventStore\EventData;
use Prooph\EventStore\EventId;
use Prooph\EventStore\RecordedEvent;

class MessageTransformer
{
    protected $messageMap = [];

    // key = event-type, value = class-name
    public function __construct(array $messageMap)
    {
        $this->messageMap = $messageMap;
    }

    public function toMessage(RecordedEvent $event): Message
    {
        $type = $event->eventType();

        if (isset($this->messageMap[$type])) {
            $messageClass = $this->messageMap[$type];
        } else {
            throw new \InvalidArgumentException('Unknown event type given: ' . $type);
        }

        if (! class_exists($messageClass)) {
            throw new \UnexpectedValueException('Given message name is not a valid class: ' . (string) $messageClass);
        }

        if (! is_subclass_of($messageClass, DomainMessage::class)) {
            throw new \UnexpectedValueException(sprintf(
                'Message class %s is not a sub class of %s',
                $messageClass,
                DomainMessage::class
            ));
        }

        return $messageClass::fromArray([
            'uui' => $event->eventId()->toString(),
            'message_name' => $event->eventType(),
            'payload' => json_decode($event->data(), true),
            'metadata' => json_decode($event->metadata(), true),
            'created' => $event->created(),
        ]);
    }

    public function toEventData(Message $message): EventData
    {
        return new EventData(
            EventId::fromString($message->uuid()->toString()),
            $message->messageName(),
            true,
            json_encode($message->payload()),
            json_encode($message->metadata())
        );
    }
}
