<?php

declare(strict_types=1);

namespace Prooph\EventStore\Transport\Tcp;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputStream;
use Amp\Promise;
use Amp\TimeoutException;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use Ramsey\Uuid\Uuid;

/** @internal */
class TcpDispatcher
{
    /** @var OutputStream */
    private $stream;
    /** @var int */
    private $operationTimeout;

    public function __construct(OutputStream $stream, int $operationTimeout)
    {
        $this->stream = $stream;
        $this->operationTimeout = $operationTimeout;
    }

    public function compose(
        TcpCommand $command,
        Message $data = null,
        string $correlationId = null,
        UserCredentials $credentials = null
    ): TcpPackage {
        if (null === $correlationId) {
            $correlationId = $this->createCorrelationId();
        }

        return new TcpPackage(
            $command,
            $credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $data,
            $credentials
        );
    }

    /**
     * @throws ClosedException
     * @throws TimeoutException
     */
    public function dispatch(TcpPackage $package): Promise
    {
        $promise = $this->stream->write($this->encode($package));

        return Promise\timeout($promise, $this->operationTimeout);
    }

    /**
     * @throws ClosedException
     * @throws TimeoutException
     */
    public function composeAndDispatch(
        TcpCommand $command,
        Message $data = null,
        string $correlationId = null,
        UserCredentials $credentials = null
    ): Promise {
        return $this->dispatch($this->compose($command, $data, $correlationId, $credentials));
    }

    private function encode(TcpPackage $package): string
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

    public function createCorrelationId(): string
    {
        return \str_replace('-', '', Uuid::uuid4()->toString());
    }
}
