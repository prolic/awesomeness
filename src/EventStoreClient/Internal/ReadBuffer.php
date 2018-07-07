<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\ByteStream\InputStream;
use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use Generator;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpOffset;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStore\Transport\Tcp\TcpPackageFactory;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;

/** @internal */
class ReadBuffer
{
    private const HeartBeatCorrelationId = 'heartbeat';

    /** @var InputStream */
    private $inputStream;
    /** @var int */
    private $operationTimeout;
    /** @var TcpPackageFactory */
    private $tcpPackageFactory;
    /** @var string */
    private $currentMessage;
    /** @var TcpPackage[] */
    private $ready = [];
    /** @var Deferred[] */
    private $waiting = [];

    public function __construct(InputStream $inputStream, int $operationTimeout)
    {
        $this->inputStream = $inputStream;
        $this->operationTimeout = $operationTimeout;
        $this->tcpPackageFactory = new TcpPackageFactory();

        Loop::onReadable($inputStream->getResource(), function (string $watcher): Generator {
            $value = yield $this->inputStream->read();

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

    /**
     * @return Promise<TcpPackage>
     */
    public function waitFor(string $correlationId): Promise
    {
        if (isset($this->ready[$correlationId])) {
            $package = $this->ready[$correlationId];
            unset($this->ready[$correlationId]);

            return new Success($package);
        }

        $deferred = new Deferred();
        $promise = Promise\timeout($deferred->promise(), $this->operationTimeout);
        $this->waiting[$correlationId] = $deferred;

        return $promise;
    }

    /**
     * @return Promise<TcpPackage>
     */
    public function waitForHeartBeat(): Promise
    {
        return $this->waitFor(self::HeartBeatCorrelationId);
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

        if ($package->command()->equals(TcpCommand::heartbeatResponseCommand())) {
            $correlationId = self::HeartBeatCorrelationId;
        } else {
            $correlationId = $package->correlationId();
        }

        if (isset($this->waiting[$correlationId])) {
            $this->waiting[$correlationId]->resolve($package);
            unset($this->waiting[$correlationId]);
        }

        $this->ready[$correlationId] = $package;
        unset($this->ready[$correlationId]);
    }
}
