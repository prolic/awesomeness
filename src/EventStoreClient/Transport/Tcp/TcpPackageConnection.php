<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Transport\Tcp;

use Amp\ByteStream\ClosedException;
use Amp\ByteStream\OutputStream;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\ClientSocket;
use Amp\Socket\ConnectException;
use Amp\TimeoutException;
use Generator;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpOffset;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Internal\ByteBuffer\Buffer;
use Ramsey\Uuid\Uuid;
use function Amp\call;
use function Amp\Socket\connect;

/** @internal */
class TcpPackageConnection
{
    private const ClientConnectionTimeout = 1000; // milliseconds

    /** @var IpEndPoint */
    private $remoteEndPoint;
    /** bool */
    private $ssl;
    /** @var ClientSocket */
    private $connection;

    private $tcpPackageMessageHandler;
    private $tcpConnectionErrorMessageHandler;
    private $tcpConnectionEstablishedMessageHandler;
    private $tcpConnectionClosedMessageHandler;

    public function __construct(
        IpEndPoint $remoteEndPoint,
        bool $ssl,
        callable $tcpPackageMessageHandler,
        callable $tcpConnectionErrorMessageHandler,
        callable $tcpConnectionEstablishedMessageHandler,
        callable $tcpConnectionClosedMessageHandler
    )
    {
        $this->remoteEndPoint = $remoteEndPoint;
        $this->ssl = $ssl;
        $this->tcpPackageMessageHandler = $tcpPackageMessageHandler;
        $this->tcpConnectionErrorMessageHandler = $tcpConnectionErrorMessageHandler;
        $this->tcpConnectionEstablishedMessageHandler = $tcpConnectionEstablishedMessageHandler;
        $this->tcpConnectionClosedMessageHandler = $tcpConnectionClosedMessageHandler;
    }

    public function remoteEndPoint(): IpEndPoint
    {
        return $this->remoteEndPoint;
    }

    public function connectAsync(): Promise
    {
        return call(function (): Generator {
            try {
                $context = (new ClientConnectContext())->withConnectTimeout(self::ClientConnectionTimeout);
                $uri = \sprintf('tcp://%s:%s', $this->remoteEndPoint->host(), $this->remoteEndPoint->port());
                $this->connection = yield connect($uri, $context);

                if ($this->ssl) {
                    yield $this->connection->enableCrypto();
                }

                ($this->tcpConnectionEstablishedMessageHandler)($this);
            } catch (ConnectException $e) {
                ($this->tcpConnectionClosedMessageHandler)($this, $e);
            } catch (\Throwable $e) {
                ($this->tcpConnectionErrorMessageHandler)($this, $e);
            }

            /*
            $this->readBuffer = new ReadBuffer($this->connection, $this->settings->operationTimeout());
            $this->manageHeartBeats();
            */
        });
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
     */
    public function sendAsync(TcpPackage $package): Promise
    {
        try {
            return $this->connection->write($this->encode($package));
        } catch (ClosedException $e) {
            ($this->tcpConnectionClosedMessageHandler)($this, $e);
        }
    }

    public function startReceiving(): void
    {
        // @todo implement (see current ReadBuffer)
    }

    public function close(): void
    {
        $this->connection->close();
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
