<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Generator;
use Prooph\EventStoreClient\ByteBuffer\Buffer;
use Prooph\EventStoreClient\Internal\HandlerFactory;
use Prooph\EventStoreClient\Internal\Message\MessageConfiguration;
use Prooph\EventStoreClient\Internal\Message\MessageType;
use Prooph\EventStoreClient\Internal\Message\SocketMessage;
use function Amp\call;

class ReadBuffer
{
    /** @var InputStream */
    private $inputStream;
    /** @var HandlerFactory */
    private $handlerFactory;
    /** @var  string */
    private $currentMessage;

    public function __construct(InputStream $inputStream)
    {
        $this->inputStream = $inputStream;
        $this->handlerFactory = new HandlerFactory();
    }

    /**
     * @return Promise<SocketMessage[]>
     */
    public function waitFor(string $correlationId): Promise
    {
        return call(function () use ($correlationId): Generator {
            $socketMessages = [];

            do {
                $value = yield $this->inputStream->read();

                $buffer = Buffer::fromString($value);
                $dataLength = \strlen($value);
                $messageLength = $buffer->readInt32LE(0) + MessageConfiguration::Int32Length;

                if ($dataLength === $messageLength) {
                    $socketMessages[] = $this->decomposeMessage($value);
                    $this->currentMessage = null;
                } elseif ($dataLength > $messageLength) {
                    $message = \substr($value, 0, $messageLength);
                    $socketMessages[] = $this->decomposeMessage($message);

                    // reset data to next message
                    $value = \substr($value, $messageLength, $dataLength);
                    $this->currentMessage = $value;
                } else {
                    $this->currentMessage .= $value;
                }
            } while ($dataLength > $messageLength);

            return $socketMessages;
        });
    }

    private function decomposeMessage(string $message): SocketMessage
    {
        $buffer = Buffer::fromString($message);

        // Information about how long the message is to help decode it. (comes from the server)
        // $messageLength = (whole stream length) - (4 bytes for saved length).
        $messageLength = $buffer->readInt32LE(0);

        $messageType = new MessageType($buffer->readInt8(MessageConfiguration::MessageTypeOffset));
        $buffer->readInt8(MessageConfiguration::FlagOffset); // flag
        $correlationID = \bin2hex($buffer->read(MessageConfiguration::CorrelationIdOffset, MessageConfiguration::CorrelationIdLength));
        $data = $buffer->read(MessageConfiguration::DataOffset, $messageLength - MessageConfiguration::HeaderLenth);

        $handler = $this->handlerFactory->create($messageType);

        return $handler->handle($messageType, $correlationID, $data);
    }
}
