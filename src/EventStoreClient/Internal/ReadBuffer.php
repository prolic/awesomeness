<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Loop;
use Amp\Socket\ClientSocket;
use Generator;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpOffset;
use Prooph\EventStore\Transport\Tcp\TcpPackageFactory;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;

/** @internal */
class ReadBuffer
{
    /** @var ClientSocket */
    private $socket;
    /** @var string */
    private $currentMessage;
    /** @var callable */
    private $messageHandler;
    /** @var TcpPackageFactory */
    private $tcpPackageFactory;

    public function __construct(ClientSocket $socket, callable $messageHandler)
    {
        $this->socket = $socket;
        $this->messageHandler = $messageHandler;
        $this->tcpPackageFactory = new TcpPackageFactory();
    }

    public function startReceivingMessages(): void
    {
        Loop::onReadable($this->socket->getResource(), function (string $watcher): Generator {
            $value = yield $this->socket->read();

            if (null === $value) {
                // stream got closed
                Loop::disable($watcher);

                return;
            }

            if (null !== $this->currentMessage) {
                $value = $this->currentMessage . $value;
            }

            $buffer = Buffer::fromString($value);
            $dataLength = \strlen($value);
            $messageLength = $buffer->readInt32LE(0) + TcpOffset::Int32Length;

            if ($dataLength === $messageLength) {
                $this->handleMessage($value);
                $this->currentMessage = null;
            } elseif ($dataLength > $messageLength) {
                $message = \substr($value, 0, $messageLength);
                $this->handleMessage($message);

                // reset data to next message
                $value = \substr($value, $messageLength, $dataLength);
                $this->currentMessage = $value;
            } else {
                $this->currentMessage = $value;
            }
        });
    }

    private function handleMessage(string $message): void
    {
        $buffer = Buffer::fromString($message);

        // Information about how long the message is to help decode it. (comes from the server)
        // $messageLength = (whole stream length) - (4 bytes for saved length).
        $messageLength = $buffer->readInt32LE(0);

        $command = TcpCommand::fromValue($buffer->readInt8(TcpOffset::MessageTypeOffset));
        $flags = TcpFlags::fromValue($buffer->readInt8(TcpOffset::FlagOffset));
        $correlationId = \bin2hex($buffer->read(TcpOffset::CorrelationIdOffset, TcpOffset::CorrelationIdLength));
        $data = $buffer->read(TcpOffset::DataOffset, $messageLength - TcpOffset::HeaderLenth);

        $package = $this->tcpPackageFactory->build($command, $flags, $correlationId, $data);

        ($this->messageHandler)($package);
    }
}
