<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Loop;
use Amp\Socket\ClientSocket;
use Generator;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
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
            $messageLength = $buffer->readInt32LE(0) + 4; // 4 = size of int32LE

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

        $messageLength = $buffer->readInt32LE(0);

        $command = TcpCommand::fromValue($buffer->readInt8(TcpPackage::DataOffset));
        $flags = TcpFlags::fromValue($buffer->readInt8(TcpPackage::DataOffset + TcpPackage::FlagsOffset));
        $correlationId = \bin2hex($buffer->read(TcpPackage::DataOffset + TcpPackage::CorrelationOffset, TcpPackage::AuthOffset - TcpPackage::CorrelationOffset));
        $headerSize = TcpPackage::MandatorySize;
        $credentials = null;

        if ($flags->equals(TcpFlags::authenticated())) {
            $loginLength = 4 + TcpPackage::AuthOffset;

            if (TcpPackage::AuthOffset + 1 + $loginLength + 1 >= $messageLength) {
                throw new \Exception('Login length is too big, it does not fit into TcpPackage');
            }

            $login = $buffer->read(TcpPackage::DataOffset + TcpPackage::AuthOffset + 1, $loginLength);

            $passwordLength = TcpPackage::DataOffset + TcpPackage::AuthOffset + 1 + $loginLength;

            if (TcpPackage::AuthOffset + 1 + $loginLength + 1 + $passwordLength > $messageLength) {
                throw new \Exception('Password length is too big, it does not fit into TcpPackage');
            }

            $password = $buffer->read($passwordLength + 1, $passwordLength);

            $headerSize += 1 + $loginLength + 1 + $passwordLength;

            \var_dump($login, $password); // @todo debug this
            $credentials = new UserCredentials($login, $password);
        }

        $data = $buffer->read(TcpPackage::DataOffset + $headerSize, $messageLength - $headerSize);

        $package = $this->tcpPackageFactory->build($command, $flags, $correlationId, $data, $credentials);

        ($this->messageHandler)($package);
    }
}
