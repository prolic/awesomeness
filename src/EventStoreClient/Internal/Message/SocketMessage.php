<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Google\Protobuf\Internal\Message;

class SocketMessage
{
    /** @var MessageType */
    private $messageType;

    /** @var string */
    private $correlationId;

    /** @var Message|null */
    private $data;

    /** @var Credentials|null */
    private $credentials;

    public function __construct(
        MessageType $messageType,
        string $correlationID,
        Message $data = null,
        Credentials $credentials = null
    ) {
        $this->messageType = $messageType;
        $this->correlationId = $correlationID;
        $this->data = $data;
        $this->credentials = $credentials;
    }

    public function withData(Message $data): SocketMessage
    {
        return new self($this->messageType, $this->correlationId, $data, $this->credentials);
    }

    public function withMessageType(MessageType $messageType): SocketMessage
    {
        return new self($messageType, $this->correlationId, $this->data, $this->credentials);
    }

    public function messageType(): MessageType
    {
        return $this->messageType;
    }

    public function correlationId(): string
    {
        return $this->correlationId;
    }

    public function data(): ?Message
    {
        return $this->data;
    }

    public function credentials(): ?Credentials
    {
        return $this->credentials;
    }
}
