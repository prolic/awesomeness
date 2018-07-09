<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Deferred;
use Amp\Loop;
use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;
use Amp\TimeoutException;
use Generator;
use Prooph\EventStore\Common\SystemStreams;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\EventReadStatus;
use Prooph\EventStore\Data\PersistentSubscriptionSettings;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\StreamMetadata;
use Prooph\EventStore\Data\StreamMetadataResult;
use Prooph\EventStore\Data\SystemSettings;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\EventStoreAsyncConnection as AsyncConnection;
use Prooph\EventStore\EventStoreAsyncSubscriptionConnection as AsyncSubscriptionConnection;
use Prooph\EventStore\EventStoreAsyncTransaction;
use Prooph\EventStore\EventStoreAsyncTransactionConnection as AsyncTransactionConnection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\Exception\OutOfRangeException;
use Prooph\EventStore\Exception\UnexpectedValueException;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\Exception\HeartBeatTimedOutException;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\ClientOperations\AppendToStreamOperation;
use Prooph\EventStoreClient\Internal\ClientOperations\DeleteStreamOperation;
use Prooph\EventStoreClient\Internal\ClientOperations\ReadEventOperation;
use Prooph\EventStoreClient\Internal\ClientOperations\ReadStreamEventsBackwardOperation;
use Prooph\EventStoreClient\Internal\ClientOperations\ReadStreamEventsForwardOperation;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use function Amp\call;
use function Amp\Socket\connect;

// @todo: maybe introduce EventStoreConnectionLogicHandler ?
// this would also make tcp package inspection results finally useful
// this would also make connection reconnects
// this would also utilize max retries (would need to be added to config)
// note: operation timeouts already in place via ReadBuffer implementation
final class EventStoreAsyncConnection implements
    AsyncConnection,
    AsyncTransactionConnection,
    AsyncSubscriptionConnection
{
    private const PositionStart = 0;
    private const PositionEnd = -1;
    private const PositionLatest = 999999;

    /** @var ConnectionSettings */
    private $settings;
    /** @var Socket */
    private $connection;
    /** @var ReadBuffer */
    private $readBuffer;
    /** @var TcpDispatcher */
    private $dispatcher;
    /** @var string */
    private $heartbeatWatcher;

    public function __construct(ConnectionSettings $settings)
    {
        $this->settings = $settings;
    }

    public function connectAsync(): Promise
    {
        return call(function (): Generator {
            $context = (new ClientConnectContext())->withConnectTimeout($this->settings->operationTimeout());
            $this->connection = yield connect($this->settings->uri(), $context);

            if ($this->settings->useSslConnection()) {
                yield $this->connection->enableCrypto();
            }

            $this->readBuffer = new ReadBuffer($this->connection, $this->settings->operationTimeout());
            $this->dispatcher = new TcpDispatcher($this->connection, $this->settings->operationTimeout());
            $this->manageHeartBeats();
        });
    }

    public function close(): void
    {
        if ($this->connection) {
            Loop::cancel($this->heartbeatWatcher);
            $this->connection->close();
        }
    }

    public function deleteStreamAsync(
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new DeleteStreamOperation(
            $this->dispatcher,
            $this->readBuffer,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $hardDelete,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation();
    }

    /**
     * @param string $stream
     * @param int $expectedVersion
     * @param EventData[] $events
     * @param UserCredentials|null $userCredentials
     * @return Promise
     */
    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new AppendToStreamOperation(
            $this->dispatcher,
            $this->readBuffer,
            $this->settings->requireMaster(),
            $stream,
            $expectedVersion,
            $events,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation();
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $operation = new ReadEventOperation(
            $this->dispatcher,
            $this->readBuffer,
            $this->settings->requireMaster(),
            $stream,
            $eventNumber,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation();
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $operation = new ReadStreamEventsForwardOperation(
            $this->dispatcher,
            $this->readBuffer,
            $this->settings->requireMaster(),
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation();
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $operation = new ReadStreamEventsBackwardOperation(
            $this->dispatcher,
            $this->readBuffer,
            $this->settings->requireMaster(),
            $stream,
            $start,
            $count,
            $resolveLinkTos,
            $userCredentials ?? $this->settings->defaultUserCredentials()
        );

        return $operation();
    }

    public function readAllEventsForward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement readAllEventsForward() method.
    }

    public function readAllEventsBackward(
        Position $position,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement readAllEventsBackward() method.
    }

    public function setStreamMetadataAsync(
        string $stream,
        int $expectedMetaStreamVersion,
        StreamMetadata $metadata,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement setStreamMetadataAsync() method.
    }

    public function getStreamMetadataAsync(string $stream, UserCredentials $userCredentials = null): Promise
    {
        if (empty($stream)) {
            throw new InvalidArgumentException('Stream cannot be empty');
        }

        $readEventPromise = $this->readEventAsync(
            SystemStreams::metastreamOf($stream),
            -1,
            false,
            $userCredentials
        );

        $deferred = new Deferred();

        $readEventPromise->onResolve(function (?\Throwable $e, $eventReadResult) use ($stream, $deferred) {
            if ($e) {
                $deferred->fail($e);
            }

            /** @var EventReadResult $eventReadResult */

            switch ($eventReadResult->status()->value()) {
                case EventReadStatus::Success:
                    $event = $eventReadResult->event();

                    if (null === $event) {
                        throw new UnexpectedValueException('Event is null while operation result is Success');
                    }

                    $event = $event->originalEvent();

                    $deferred->resolve(new StreamMetadataResult(
                        $stream,
                        false,
                        $event ? $event->eventNumber() : -1,
                        $event ? $event->data() : ''
                    ));
                    break;
                case EventReadStatus::NotFound:
                case EventReadStatus::NoStream:
                    $deferred->resolve(new StreamMetadataResult($stream, false, -1, ''));
                    break;
                case EventReadStatus::StreamDeleted:
                    $deferred->resolve(new StreamMetadataResult($stream, true, PHP_INT_MAX, ''));
                    break;
                default:
                    $deferred->fail(new OutOfRangeException('Unexpected ReadEventResult: ' . $eventReadResult->status()->value()));
                    break;
            }
        });

        return $deferred->promise();
    }

    public function setSystemSettingsAsync(SystemSettings $settings, UserCredentials $userCredentials = null): Promise
    {
        // TODO: Implement setSystemSettingsAsync() method.
    }

    public function createPersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement createPersistentSubscriptionAsync() method.
    }

    public function updatePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        PersistentSubscriptionSettings $settings,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement updatePersistentSubscriptionAsync() method.
    }

    public function deletePersistentSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement deletePersistentSubscriptionAsync() method.
    }

    public function connectToPersistentSubscription(
        string $stream,
        string $groupName,
        callable $eventAppeared,
        callable $subscriptionDropped = null,
        int $bufferSize = 10,
        bool $autoAck = true,
        UserCredentials $userCredentials = null
    ): EventStorePersistentSubscription {
        // TODO: Implement connectToPersistentSubscription() method.
    }

    public function replayParkedAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement replayParkedAsync() method.
    }

    public function getInformationForAllSubscriptionsAsync(
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement getInformationForAllSubscriptionsAsync() method.
    }

    public function getInformationForSubscriptionsWithStreamAsync(
        string $stream,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement getInformationForSubscriptionsWithStreamAsync() method.
    }

    public function getInformationForSubscriptionAsync(
        string $stream,
        string $groupName,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement getInformationForSubscriptionAsync() method.
    }

    public function startTransactionAsync(
        string $stream,
        int $expectedVersion,
        UserCredentials $userCredentials = null
    ): EventStoreAsyncTransaction {
        // TODO: Implement startTransactionAsync() method.
    }

    public function transactionalWriteAsync(
        EventStoreAsyncTransaction $transaction,
        array $events,
        ?UserCredentials $userCredentials
    ): Promise {
        // TODO: Implement transactionalWriteAsync() method.
    }

    public function commitTransactionAsync(
        EventStoreAsyncTransaction $transaction,
        ?UserCredentials $userCredentials
    ): Promise {
        // TODO: Implement commitTransactionAsync() method.
    }

    private function manageHeartBeats(): void
    {
        $this->heartbeatWatcher = Loop::repeat(
            $this->settings->heartbeatInterval(),
            function (string $watcher): Generator {
                yield $this->dispatcher->composeAndDispatch(
                    TcpCommand::heartbeatRequestCommand()
                );
                try {
                    yield Promise\timeout($this->readBuffer->waitForHeartBeat(), $this->settings->heartbeatTimeout());
                } catch (TimeoutException $e) {
                    Loop::disable($watcher);
                    $this->close();
                    throw new HeartBeatTimedOutException();
                }
            }
        );
    }
}
