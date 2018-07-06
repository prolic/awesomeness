<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputStream;
use Amp\Promise;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpOffset;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use Ramsey\Uuid\Uuid;
use SplQueue;

/** @internal */
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

    public function compose(TcpCommand $tcpCommand, Message $data = null, string $correlationId = null): TcpPackage
    {
        return new TcpPackage(
            $tcpCommand,
            $this->credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId($correlationId),
            $data,
            $this->credentials
        );
    }

    /** @throws ClosedException */
    public function write(TcpPackage $package): Promise
    {
        return $this->stream->write($this->encode($package));
    }

    /** @throws ClosedException */
    public function composeAndWrite(TcpCommand $tcpCommand, Message $data = null, $correlationId = null): Promise
    {
        return $this->write($this->compose($tcpCommand, $data, $correlationId));
    }

    public function encode(TcpPackage $package): string
    {
        $messageLength = TcpOffset::HeaderLenth;

        $credentials = $package->credentials();
        $doAuthorization = $credentials ? true : false;
        $authorizationLength = 0;

        if ($doAuthorization) {
            $authorizationLength = 1 + \strlen($credentials->username()) + 1 + \strlen($credentials->password());
        }

        $dataToSend = $package->data();

        if ($dataToSend) {
            $dataToSend = $dataToSend->serializeToString();
            $messageLength += \strlen($dataToSend);
        }

        $wholeMessageLength = $messageLength + $authorizationLength + TcpOffset::Int32Length;

        $buffer = Buffer::withSize($wholeMessageLength);
        $buffer->writeInt32LE($messageLength + $authorizationLength, 0);
        $buffer->writeInt8($package->command()->value(), TcpOffset::MessageTypeOffset);
        $buffer->writeInt8(($doAuthorization ? TcpFlags::Authenticated : TcpFlags::None), TcpOffset::FlagOffset);
        $buffer->write(\pack('H*', $package->correlationId()), TcpOffset::CorrelationIdOffset);

        if ($doAuthorization) {
            $usernameLength = \strlen($credentials->username());
            $passwordLength = \strlen($credentials->password());

            $buffer->writeInt8($usernameLength, TcpOffset::DataOffset);
            $buffer->write($credentials->username(), TcpOffset::DataOffset + 1);
            $buffer->writeInt8($passwordLength, TcpOffset::DataOffset + 1 + $usernameLength);
            $buffer->write($credentials->password(), TcpOffset::DataOffset + 1 + $usernameLength + 1);
        }

        if ($dataToSend) {
            $buffer->write($dataToSend, TcpOffset::DataOffset + $authorizationLength);
        }

        return $buffer->__toString();
    }

    public function correlationId(string $uuid = null): string
    {
        return $uuid ?: \str_replace('-', '', Uuid::uuid4()->toString());
    }
}
