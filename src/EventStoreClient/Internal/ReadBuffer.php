<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\ByteStream\InputStream;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpOffset;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStore\Transport\Tcp\TcpPackageFactory;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use function Amp\call;

/** @internal */
class ReadBuffer
{
    /** @var InputStream */
    private $inputStream;
    /** @var TcpPackageFactory */
    private $tcpPackageFactory;
    /** @var string */
    private $currentMessage;

    public function __construct(InputStream $inputStream)
    {
        $this->inputStream = $inputStream;
        $this->tcpPackageFactory = new TcpPackageFactory();
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
                $messageLength = $buffer->readInt32LE(0) + TcpOffset::Int32Length;

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

    private function decomposeMessage(string $message): TcpPackage
    {
        $buffer = Buffer::fromString($message);

        // Information about how long the message is to help decode it. (comes from the server)
        // $messageLength = (whole stream length) - (4 bytes for saved length).
        $messageLength = $buffer->readInt32LE(0);

        $command = TcpCommand::fromValue($buffer->readInt8(TcpOffset::MessageTypeOffset));
        $flags = TcpFlags::fromValue($buffer->readInt8(TcpOffset::FlagOffset));
        $correlationId = \bin2hex($buffer->read(TcpOffset::CorrelationIdOffset, TcpOffset::CorrelationIdLength));
        $data = $buffer->read(TcpOffset::DataOffset, $messageLength - TcpOffset::HeaderLenth);

        return $this->tcpPackageFactory->build($command, $flags, $correlationId, $data);
    }
}
