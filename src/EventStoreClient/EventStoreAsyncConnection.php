<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Amp\Promise;
use Amp\Socket\ClientConnectContext;
use Amp\Socket\Socket;
use Generator;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\EventStoreAsyncConnection as AsyncConnection;
use Prooph\EventStore\EventStoreAsyncSubscriptionConnection as AsyncSubscriptionConnection;
use Prooph\EventStore\EventStoreAsyncTransaction;
use Prooph\EventStore\EventStoreAsyncTransactionConnection as AsyncTransactionConnection;
use Prooph\EventStore\EventStorePersistentSubscription;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\Internal\Messages\ReadStreamEvents;
use Prooph\EventStore\Internal\Messages\ReadStreamEventsCompleted;
use Prooph\EventStore\Internal\Messages\ResolvedIndexedEvent;
use Prooph\EventStore\Messages\ResolvedIndexedEvent as ResolvedIndexedEventMessage;
use Prooph\EventStore\PersistentSubscriptionSettings;
use Prooph\EventStore\Position;
use Prooph\EventStore\ReadDirection;
use Prooph\EventStore\ResolvedEvent;
use Prooph\EventStore\SliceReadStatus;
use Prooph\EventStore\StreamEventsSlice;
use Prooph\EventStore\StreamMetadata;
use Prooph\EventStore\SystemSettings;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStore\UserCredentials;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\EventRecordConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use function Amp\call;
use function Amp\Socket\connect;

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
        });
    }

    public function close(): void
    {
        if ($this->connection) {
            $this->connection->close();
        }
    }

    public function deleteStreamAsync(
        string $stream,
        bool $hardDelete,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement deleteStreamAsync() method.
    }

    public function appendToStreamAsync(
        string $stream,
        int $expectedVersion,
        array $events,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement appendToStreamAsync() method.
    }

    public function readEventAsync(
        string $stream,
        int $eventNumber,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement readEventAsync() method.
    }

    public function readStreamEventsForwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        if ($count > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Count should be less than %s. For larger reads you should page.',
                Consts::MaxReadSize
            ));
        }

        $query = new ReadStreamEvents();
        $query->setRequireMaster($this->settings->requireMaster());
        $query->setEventStreamId($stream);
        $query->setFromEventNumber($start);
        $query->setMaxCount($count);
        $query->setResolveLinkTos($resolveLinkTos);

        return $this->readEvents(
            $query,
            ReadDirection::forward(),
            TcpCommand::readStreamEventsForward()
        );
    }

    public function readStreamEventsBackwardAsync(
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos = true,
        UserCredentials $userCredentials = null
    ): Promise {
        // TODO: Implement readStreamEventsBackwardAsync() method.
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
        // TODO: Implement getStreamMetadataAsync() method.
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

    /**
     * @param TcpDispatcher $dispatcher
     * @param Message $query
     * @param ReadDirection $readDirection
     * @param TcpCommand $command
     * @param TcpPackage[] $packages
     * @return Promise
     */
    private function readEvents(
        Message $query,
        ReadDirection $readDirection,
        TcpCommand $command,
        array $packages = []
    ): Promise {
        /** @var ReadStreamEvents $query */
        $originalFrom = $query->getFromEventNumber();

        return call(function () use ($query, $readDirection, $command, $packages, $originalFrom): Generator {
            $correlationId = $this->dispatcher->createCorrelationId();
            $max = $query->getMaxCount();
            $asked = $max;

            yield $this->dispatcher->composeAndDispatch($command, $query, $correlationId);

            /** @var TcpPackage $package */
            $package = yield $this->readBuffer->waitFor($correlationId);

            if ($package->command()->equals(TcpCommand::readStreamEventsForwardCompleted())) {
                $package = $package->data();
                /** @var ReadStreamEventsCompleted $package */
                if ($error = $package->getError()) {
                    throw new \RuntimeException($error);
                }

                $records = $package->getEvents();
                $asked -= \count($records);

                if (! $package->getIsEndOfStream()
                    && ! (
                        $asked <= 0 && $max !== self::PositionLatest
                    )
                ) {
                    $start = $records[\count($records) - 1];
                    /* @var ResolvedIndexedEvent $start */

                    if (null === $start->getLink()) {
                        $start = $command->equals(TcpCommand::readStreamEventsForward())
                            ? $start->getEvent()->getEventNumber() + 1
                            : $start->getEvent()->getEventNumber() - 1;
                    } else {
                        $start = $command->equals(TcpCommand::readStreamEventsForward())
                            ? $start->getLink()->getEventNumber() + 1
                            : $start->getLink()->getEventNumber() - 1;
                    }

                    $query->setFromEventNumber($start);
                    $query->setMaxCount($asked);

                    yield $this->readEvents($query, $readDirection, $command, $packages);
                }

                $resolvedEvents = [];

                foreach ($records as $record) {
                    /** @var ResolvedIndexedEvent $record */
                    $event = EventRecordConverter::convert($record->getEvent());
                    $link = null;

                    if ($link = $record->getLink()) {
                        $link = EventRecordConverter::convert($link);
                    }

                    $resolvedEvents[] = ResolvedEvent::fromResolvedIndexedEventMessage(
                        new ResolvedIndexedEventMessage($event, $link)
                    );
                }

                return new StreamEventsSlice(
                    SliceReadStatus::success(),
                    $query->getEventStreamId(),
                    $originalFrom,
                    $readDirection,
                    $resolvedEvents,
                    $package->getNextEventNumber(),
                    $package->getLastEventNumber(),
                    $package->getIsEndOfStream()
                );
            }
        });
    }
}
