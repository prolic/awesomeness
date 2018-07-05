<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputStream;
use Amp\Promise;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\ByteBuffer\Buffer;
use Prooph\EventStoreClient\Internal\Message\MessageConfiguration;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;
use Ramsey\Uuid\Uuid;
use SplQueue;

class Writer
{
    /** @var OutputStream */
    private $stream;
    /** @var UserCredentials|null */
    private $credentials;
    /** @var SplQueue */
    private $queue;

    public function __construct(OutputStream $stream, UserCredentials $credentials = null)
    {
        $this->stream = $stream;
        $this->credentials = $credentials;
        $this->queue = new SplQueue();
    }

    public function compose(int $messageType, Message $event = null, string $correlationId = null): SocketMessage
    {
        return new SocketMessage(
            new MessageType($messageType),
            $this->correlationId($correlationId),
            $event,
            $this->credentials
        );
    }

    /** @throws ClosedException */
    public function write(SocketMessage $message): Promise
    {
        return $this->stream->write($this->encode($message));
    }

    /** @throws ClosedException */
    public function composeAndWrite(int $messageType, Message $event = null, $correlationId = null): Promise
    {
        return $this->write($this->compose($messageType, $event, $correlationId));
    }

    public function encode(SocketMessage $socketMessage): string
    {
        $messageLength = MessageConfiguration::HeaderLenth;

        $credentials = $socketMessage->credentials();
        $doAuthorization = $credentials ? true : false;
        $authorizationLength = 0;

        if ($doAuthorization) {
            $authorizationLength = 1 + \strlen($credentials->username()) + 1 + \strlen($credentials->password());
        }

        $dataToSend = $socketMessage->data();

        if ($dataToSend) {
            $dataToSend = $dataToSend->serializeToString();
            $messageLength += \strlen($dataToSend);
        }

        $wholeMessageLength = $messageLength + $authorizationLength + MessageConfiguration::Int32Length;

        $buffer = Buffer::withSize($wholeMessageLength);
        $buffer->writeInt32LE($messageLength + $authorizationLength, 0);
        $buffer->writeInt8($socketMessage->messageType()->type(), MessageConfiguration::MessageTypeOffset);
        $buffer->writeInt8(($doAuthorization ? MessageConfiguration::FlagsAuthorization : MessageConfiguration::FlagsNone), MessageConfiguration::FlagOffset);
        $buffer->write(\pack('H*', $socketMessage->correlationId()), MessageConfiguration::CorrelationIdOffset);

        if ($doAuthorization) {
            $usernameLength = \strlen($credentials->username());
            $passwordLength = \strlen($credentials->password());

            $buffer->writeInt8($usernameLength, MessageConfiguration::DataOffset);
            $buffer->write($credentials->username(), MessageConfiguration::DataOffset + 1);
            $buffer->writeInt8($passwordLength, MessageConfiguration::DataOffset + 1 + $usernameLength);
            $buffer->write($credentials->password(), MessageConfiguration::DataOffset + 1 + $usernameLength + 1);
        }

        if ($dataToSend) {
            $buffer->write($dataToSend, MessageConfiguration::DataOffset + $authorizationLength);
        }

        return $buffer->__toString();
    }

    private function correlationId(string $uuid = null)
    {
        return $uuid ?: \str_replace('-', '', Uuid::uuid4()->toString());
    }
}
