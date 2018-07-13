<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Amp\Loop;
use Generator;
use Prooph\EventStore\Exception\ConnectionClosedException;
use Prooph\EventStore\Internal\Consts;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\Messages\IdentifyClient;
use Prooph\EventStore\NodeEndPoints;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStore\Internal\Event\ClientAuthenticationFailedEventArgs;
use Prooph\EventStore\Internal\Event\ClientClosedEventArgs;
use Prooph\EventStore\Internal\Event\ClientConnectionEventArgs;
use Prooph\EventStore\Internal\Event\ClientErrorEventArgs;
use Prooph\EventStore\Internal\Event\ClientReconnectingEventArgs;
use Prooph\EventStore\Internal\Event\ListenerHandler;
use Prooph\EventStoreClient\EventStoreAsyncNodeConnection;
use Prooph\EventStoreClient\Exception\CannotEstablishConnectionException;
use Prooph\EventStoreClient\Exception\EventStoreConnectionException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Internal\Message\CloseConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\EstablishTcpConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\HandleTcpPackageMessage;
use Prooph\EventStoreClient\Internal\Message\StartConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\StartOperationMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionClosedMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionErrorMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionEstablishedMessage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use Throwable;

/** @internal */
class EventStoreConnectionLogicHandler
{
    private const ClientVersion = 1;

    /** @var EventStoreAsyncNodeConnection */
    private $esConnection;
    /** @var TcpPackageConnection */
    private $connection;
    /** @var ConnectionSettings */
    private $settings;
    /** @var ConnectionState */
    private $state;
    /** @var ConnectingPhase */
    private $connectingPhase;
    /** @var EndPointDiscoverer */
    private $endPointDiscoverer;
    /** @var MessageHandler */
    private $handler;
    /** @var OperationsManager */
    private $operations;
    /** @var EventHandler */
    private $eventHandler;
    /** @var StopWatch */
    private $stopWatch;
    /** @var string */
    private $timerTickWatcherId;

    /** @var ReconnectionInfo */
    private $reconnInfo;
    /** @var HeartbeatInfo */
    private $heartbeatInfo;
    /** @var AuthInfo */
    private $authInfo;
    /** @var IdentifyInfo */
    private $identityInfo;
    /** @var bool */
    private $wasConnected = false;
    /** @var int */
    private $packageNumber = 0;
    /** @var int */
    private $lastTimeoutsTimeStamp = 0;

    public function __construct(EventStoreAsyncNodeConnection $connection, ConnectionSettings $settings)
    {
        $this->esConnection = $connection;
        $this->settings = $settings;
        $this->state = ConnectionState::init();
        $this->connectingPhase = ConnectingPhase::invalid();
        $this->handler = new MessageHandler();
        $this->operations = new OperationsManager($connection->connectionName(), $settings);
        $this->eventHandler = new EventHandler();
        $this->stopWatch = StopWatch::startNew();

        $this->handler->registerHandler(
            StartConnectionMessage::class,
            function (StartConnectionMessage $message): void {
                $this->startConnection($message->deferred(), $message->endPointDiscoverer());
            }
        );
        $this->handler->registerHandler(
            CloseConnectionMessage::class,
            function (CloseConnectionMessage $message): void {
                $this->closeConnection($message->reason(), $message->exception());
            }
        );

        $this->handler->registerHandler(
            StartOperationMessage::class,
            function (StartOperationMessage $message): void {
                $this->startOperation($message->operation(), $message->maxRetries(), $message->timeout());
            }
        );
        $this->handler->registerHandler(
            StartSubscriptionMessage::class,
            function (StartSubscriptionMessage $message): void {
                $this->startSubscription($message);
            }
        );
        $this->handler->registerHandler(
            StartPersistentSubscriptionMessage::class,
            function (StartPersistentSubscriptionMessage $message): void {
                $this->startPersistentSubscription($message);
            }
        );

        $this->handler->registerHandler(
            EstablishTcpConnectionMessage::class,
            function (EstablishTcpConnectionMessage $message): void {
                $this->establishTcpConnection($message->nodeEndPoints());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionEstablishedMessage::class,
            function (TcpConnectionEstablishedMessage $message): void {
                $this->tcpConnectionEstablished($message->tcpPackageConnection());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionErrorMessage::class,
            function (TcpConnectionErrorMessage $message): void {
                $this->tcpConnectionError($message->tcpPackageConnection(), $message->exception());
            }
        );
        $this->handler->registerHandler(
            TcpConnectionClosedMessage::class,
            function (TcpConnectionClosedMessage $message): void {
                $this->tcpConnectionClosed($message->tcpPackageConnection());
            }
        );
        $this->handler->registerHandler(
            HandleTcpPackageMessage::class,
            function (HandleTcpPackageMessage $message): void {
                $this->handleTcpPackage($message->tcpPackageConnection(), $message->tcpPackage());
            }
        );

        $this->timerTickWatcherId = Loop::repeat(Consts::TimerPeriod, function (): void {
            $this->timerTick();
        });
    }

    public function totalOperationCount(): int
    {
        return $this->operations ? $this->operations->totalOperationCount() : 0;
    }

    public function enqueueMessage(Message $message): void
    {
        $this->handler->handle($message);
    }

    private function startConnection(Deferred $deferred, EndPointDiscoverer $endPointDiscoverer): void
    {
        switch ($this->state->value()) {
            case ConnectionState::Init:
                $this->endPointDiscoverer = $endPointDiscoverer;
                $this->state = ConnectionState::connecting();
                $this->connectingPhase = ConnectingPhase::reconnecting();
                $this->discoverEndPoint($deferred);
                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $deferred->fail(new InvalidOperationException(\sprintf(
                    'EventStoreNodeConnection \'%s\' is already active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::Closed:
                $deferred->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
        }
    }

    private function discoverEndPoint(?Deferred $deferred): void
    {
        if (! $this->state->equals(ConnectionState::connecting())) {
            return;
        }

        if (! $this->connectingPhase->equals(ConnectingPhase::reconnecting())) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::endPointDiscovery();

        $promise = $this->endPointDiscoverer->discoverAsync(
            null !== $this->connection
                ? $this->connection->remoteEndPoint()
                : null
        );

        $promise->onResolve(function (?\Throwable $e, NodeEndPoints $endpoints = null) use ($deferred): void {
            if ($e) {
                $this->enqueueMessage(new CloseConnectionMessage(
                    'Failed to resolve TCP end point to which to connect',
                    $e
                ));

                if ($deferred) {
                    $deferred->fail(new CannotEstablishConnectionException('Cannot resolve target end point'));
                }

                return;
            }

            $this->enqueueMessage(new EstablishTcpConnectionMessage($endpoints));

            if ($deferred) {
                $deferred->resolve(null);
            }
        });
    }

    /** @throws \Exception */
    private function closeConnection(string $reason, Throwable $exception = null): void
    {
        if ($this->state->equals(ConnectionState::closed())) {
            // ignore
            return;
        }

        $this->state = ConnectionState::closed();

        Loop::cancel($this->timerTickWatcherId);
        $this->operations->cleanUp();
        //_subscriptions.CleanUp(); // @todo
        $this->closeTcpConnection($reason);

        if (null !== $exception) {
            $this->raiseErrorOccurred($exception);
        }

        $this->raiseClosed($reason);
    }

    /** @throws \Exception */
    private function establishTcpConnection(NodeEndPoints $endPoints): void
    {
        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection('No end point to node specified');

            return;
        }

        if (! $this->state->equals(ConnectionState::connecting())
            || ! $this->connectingPhase->equals(ConnectingPhase::endPointDiscovery())
        ) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::connectionEstablishing();

        $this->connection = new TcpPackageConnection(
            $endPoint,
            CorrelationIdGenerator::generate(),
            $this->settings->useSslConnection(),
            function (TcpPackageConnection $connection, TcpPackage $package): void {
                $this->enqueueMessage(new HandleTcpPackageMessage($connection, $package));
            },
            function (TcpPackageConnection $connection, \Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionErrorMessage($connection, $exception));
            },
            function (TcpPackageConnection $connection): void {
                $this->enqueueMessage(new TcpConnectionEstablishedMessage($connection));
            },
            function (TcpPackageConnection $connection, \Throwable $exception): void {
                $this->enqueueMessage(new TcpConnectionClosedMessage($connection, $exception));
            }
        );

        Loop::defer(function (): Generator {
            yield $this->connection->connectAsync();

            $this->connection->startReceiving();
        });
    }

    /** @throws \Exception */
    public function tcpConnectionError(TcpPackageConnection $tcpPackageConnection, Throwable $exception): void
    {
        if ($this->connection !== $tcpPackageConnection
            || $this->state->equals(ConnectionState::closed())
        ) {
            return;
        }

        $this->closeConnection('TCP connection error occurred', $exception);
    }

    /** @throws \Exception */
    private function closeTcpConnection(string $reason): void
    {
        if (null === $this->connection) {
            return;
        }

        $this->connection->close();

        $this->tcpConnectionClosed($this->connection);

        $this->connection = null;
    }

    /** @throws \Exception */
    private function tcpConnectionClosed(TcpPackageConnection $tcpPackageConnection): void
    {
        if ($this->state->equals(ConnectionState::init())) {
            throw new \Exception();
        }

        if ($this->state->equals(ConnectionState::closed())
            || $this->connection !== $tcpPackageConnection
        ) {
            return;
        }

        $this->state = ConnectionState::connecting();
        $this->connectingPhase = ConnectingPhase::reconnecting();

        //_subscriptions.PurgeSubscribedAndDroppedSubscriptions(_connection.ConnectionId); // @todo

        $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt(), $this->stopWatch->elapsed());

        if ($this->compareWasConnected(false, true)) {
            $this->raiseDisconnected($tcpPackageConnection->remoteEndPoint());
        }
    }

    private function tcpConnectionEstablished(TcpPackageConnection $tcpPackageConnection): void
    {
        if (! $this->state->equals(ConnectionState::connecting())
            || $this->connection !== $tcpPackageConnection
            || $this->connection->isClosed()
        ) {
            return;
        }

        $elapsed = $this->stopWatch->elapsed();

        $this->heartbeatInfo = new HeartbeatInfo($this->packageNumber, true, $elapsed);

        if ($this->settings->defaultUserCredentials() !== null) {
            $this->connectingPhase = ConnectingPhase::authentication();

            $this->authInfo = new AuthInfo(CorrelationIdGenerator::generate(), $elapsed);

            $this->connection->sendAsync(new TcpPackage(
                TcpCommand::authenticate(),
                TcpFlags::authenticated(),
                $this->authInfo->correlationId(),
                null,
                $this->settings->defaultUserCredentials()
            ));
        } else {
            $this->goToIdentifyState();
        }
    }

    private function goToIdentifyState(): void
    {
        $this->connectingPhase = ConnectingPhase::identification();
        $this->identityInfo = new IdentifyInfo(CorrelationIdGenerator::generate(), $this->stopWatch->elapsed());

        $dto = new IdentifyClient();
        $dto->setVersion(self::ClientVersion);
        $dto->setConnectionName($this->esConnection->connectionName());

        $this->connection->sendAsync(new TcpPackage(
            TcpCommand::identifyClient(),
            TcpFlags::none(),
            $this->identityInfo->correlationId(),
            $dto
        ));
    }

    private function goToConnectedState(): void
    {
        $this->state = ConnectionState::connected();
        $this->connectingPhase = ConnectingPhase::connected();

        $this->compareWasConnected(true, false);

        $this->raiseConnectedEvent($this->connection->remoteEndPoint());

        $elapsed = $this->stopWatch->elapsed();

        if ($elapsed - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
            $this->operations->checkTimeoutsAndRetry($this->connection);
            // _subscriptions.CheckTimeoutsAndRetry(_connection);
            $this->lastTimeoutsTimeStamp = $elapsed;
        }
    }

    private function timerTick(): void
    {
        $elapsed = $this->stopWatch->elapsed();

        switch ($this->state->value()) {
            case ConnectionState::Init:
                break;
            case ConnectionState::Connecting:
                if ($this->connectingPhase->equals(ConnectingPhase::reconnecting())
                    && $elapsed - $this->reconnInfo->timestamp() >= $this->settings->reconnectionDelay()
                ) {
                    $this->reconnInfo = new ReconnectionInfo($this->reconnInfo->reconnectionAttempt() + 1, DateTimeUtil::utcNow());

                    if ($this->settings->maxReconnections() >= 0 && $this->reconnInfo->reconnectionAttempt() > $this->settings->maxReconnections()) {
                        $this->closeConnection('Reconnection limit reached');
                    } else {
                        $this->raiseReconnecting();
                        $this->discoverEndPoint(null);
                    }
                }

                if ($this->connectingPhase->equals(ConnectingPhase::authentication())
                    && $elapsed - $this->authInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->raiseAuthenticationFailed('Authentication timed out');
                    $this->goToIdentifyState();
                }

                if ($this->connectingPhase->equals(ConnectingPhase::identification())
                    && $elapsed - $this->identityInfo->timestamp() >= $this->settings->operationTimeout()
                ) {
                    $this->closeTcpConnection('Timed out waiting for client to be identified');
                }

                if ($this->connectingPhase->value() > ConnectingPhase::ConnectionEstablishing) {
                    $this->manageHeartbeats();
                }

                break;
            case ConnectionState::Connected:
                if ($elapsed - $this->lastTimeoutsTimeStamp >= $this->settings->operationTimeoutCheckPeriod()) {
                    $this->reconnInfo = new ReconnectionInfo(0, $elapsed);
                    $this->operations->checkTimeoutsAndRetry($this->connection);
                    // _subscriptions.CheckTimeoutsAndRetry(_connection); // @todo
                    $this->lastTimeoutsTimeStamp = $elapsed;
                }

                $this->manageHeartbeats();

                break;
            case ConnectionState::Closed:
                break;
        }
    }

    private function manageHeartbeats(): void
    {
        if (null === $this->connection) {
            throw new \Exception();
        }

        $timeout = $this->heartbeatInfo->isIntervalStage() ? $this->settings->heartbeatInterval() : $this->settings->heartbeatTimeout();

        $elapsed = $this->stopWatch->elapsed();

        if ($elapsed - $this->heartbeatInfo->timestamp() < $timeout) {
            return;
        }

        $packageNumber = $this->packageNumber;

        if ($this->heartbeatInfo->lastPackageNumber() !== $packageNumber) {
            $this->heartbeatInfo = new HeartbeatInfo($packageNumber, true, $elapsed);

            return;
        }

        if ($this->heartbeatInfo->isIntervalStage()) {
            $this->connection->sendAsync(new TcpPackage(
                TcpCommand::heartbeatRequestCommand(),
                TcpFlags::none(),
                CorrelationIdGenerator::generate()
            ));
            $this->heartbeatInfo = new HeartbeatInfo($this->heartbeatInfo->lastPackageNumber(), false, $elapsed);
        } else {
            $msg = \sprintf(
                'EventStoreNodeConnection \'%s\': closing TCP connection [%s:%s] due to HEARTBEAT TIMEOUT at pkgNum %s',
                $this->esConnection->connectionName(),
                $this->connection->remoteEndPoint()->host(),
                $this->connection->remoteEndPoint()->host(),
                $this->packageNumber
            );

            $this->closeTcpConnection($msg);
        }
    }

    private function startOperation(ClientOperation $operation, int $maxRetries, int $timeout): void
    {
        switch ($this->state->value()) {
            case ConnectionState::Init:
                $operation->fail(new InvalidOperationException(
                    \sprintf(
                        'EventStoreNodeConnection \'%s\' is not active',
                        $this->esConnection->connectionName()
                    )
                ));
                break;
            case ConnectionState::Connecting:
                $this->operations->enqueueOperation(new OperationItem($operation, $maxRetries, $timeout));
                break;
            case ConnectionState::Connected:
                $this->operations->scheduleOperation(new OperationItem($operation, $maxRetries, $timeout), $this->connection);
                break;
            case ConnectionState::Closed:
                $operation->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
        }
    }

    private function startSubscription(StartSubscriptionMessage $message): void
    {
        // @todo
    }

    private function startPersistentSubscription(StartPersistentSubscriptionMessage $message): void
    {
        // @todo
    }

    private function handleTcpPackage(TcpPackageConnection $connection, TcpPackage $package): void
    {
        if ($this->connection !== $connection
            || $this->state->equals(ConnectionState::closed())
            || $this->state->equals(ConnectionState::init())
        ) {
            return;
        }

        ++$this->packageNumber;

        if ($package->command()->equals(TcpCommand::heartbeatResponseCommand())) {
            return;
        }

        if ($package->command()->equals(TcpCommand::heartbeatRequestCommand())) {
            $this->connection->sendAsync(new TcpPackage(
                TcpCommand::heartbeatResponseCommand(),
                TcpFlags::none(),
                $package->correlationId()
            ));

            return;
        }

        if ($package->command()->equals(TcpCommand::authenticated())
            || $package->command()->equals(TcpCommand::notAuthenticatedException())
        ) {
            if ($this->state->equals(ConnectionState::connecting())
                && $this->connectingPhase->equals(ConnectingPhase::authentication())
                && $this->authInfo->correlationId() === $package->correlationId()
            ) {
                if ($package->command()->equals(TcpCommand::notAuthenticatedException())) {
                    $this->raiseAuthenticationFailed('Not authenticated');
                }

                $this->goToIdentifyState();

                return;
            }
        }

        if ($package->command()->equals(TcpCommand::clientIdentified())
            && $this->state->equals(ConnectionState::connecting())
            && $this->identityInfo->correlationId() === $package->correlationId()
        ) {
            $this->goToConnectedState();

            return;
        }

        if ($package->command()->equals(TcpCommand::badRequest())
            && $package->correlationId() === ''
        ) {
            $exception = new EventStoreConnectionException('Base request received from server');
            $this->closeConnection('Connection-wide BadRequest received. Too dangerous to continue', $exception);

            return;
        }

        if ($operation = $this->operations->getActiveOperation($package->correlationId())) {
            $result = $operation->operation()->inspectPackage($package);

            switch ($result->inspectionDecision()->value()) {
                case InspectionDecision::DoNothing:
                    break;
                case InspectionDecision::EndOperation:
                    $this->operations->removeOperation($operation);
                    break;
                case InspectionDecision::Retry:
                    $this->operations->scheduleOperationRetry($operation);
                    break;
                case InspectionDecision::Reconnect:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    $this->operations->scheduleOperationRetry($operation);
                    break;
            }

            if ($this->state->equals(ConnectionState::connected())) {
                $this->operations->tryScheduleWaitingOperations($connection);
            }
        } elseif ($subscription = $this->subscriptions->tryGetActiveSubscription($package->correlationId())) {
            $result = $subscription->operation()->inspectPackage($package);

            switch ($result->inspectionDecision()->value()) {
                case InspectionDecision::DoNothing:
                    break;
                case InspectionDecision::EndOperation:
                    //$this->subscriptions->removeSubscription($subscription); // @todo
                    break;
                case InspectionDecision::Retry:
                    //$this->subscriptions->scheduleSubscriptionRetry($subscription); // @todo
                    break;
                case InspectionDecision::Reconnect:
                    $this->reconnectTo(new NodeEndPoints($result->tcpEndPoint(), $result->secureTcpEndPoint()));
                    //$this->subscriptions->scheduleSubscriptionRetry($subscription); // @todo
                    break;
                case InspectionDecision::Subscribed:
                    $subscription->isSubscribed = true; // @todo
                    break;
            }
        }
    }

    private function reconnectTo(NodeEndPoints $endPoints): void
    {
        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection('No end point is specified while trying to reconnect');

            return;
        }

        if (! $this->state->equals(ConnectionState::connected())
            || $this->connection->remoteEndPoint()->equals($endPoint)
        ) {
            return;
        }

        $msg = \sprintf(
            'EventStoreNodeConnection \'%s\': going to reconnect to [%s]. Current end point: [%s]',
            $this->esConnection->connectionName(),
            $endPoint->host() . ':' . $endPoint->port(),
            $this->connection->remoteEndPoint()->host() . ':' . $this->connection->remoteEndPoint()->port()
        );

        $this->closeTcpConnection($msg);

        $this->state = ConnectionState::connecting();
        $this->connectingPhase = ConnectingPhase::endPointDiscovery();

        $this->establishTcpConnection($endPoints);
    }

    private function raiseConnectedEvent(IpEndPoint $remoteEndPoint): void
    {
        $this->eventHandler->connected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseDisconnected(IpEndPoint $remoteEndPoint): void
    {
        $this->eventHandler->disconnected(new ClientConnectionEventArgs($this->esConnection, $remoteEndPoint));
    }

    private function raiseErrorOccurred(\Throwable $e): void
    {
        $this->eventHandler->errorOccurred(new ClientErrorEventArgs($this->esConnection, $e));
    }

    private function raiseClosed(string $reason): void
    {
        $this->eventHandler->closed(new ClientClosedEventArgs($this->esConnection, $reason));
    }

    private function raiseReconnecting(): void
    {
        $this->eventHandler->reconnecting(new ClientReconnectingEventArgs($this->esConnection));
    }

    private function raiseAuthenticationFailed(string $reason): void
    {
        $this->eventHandler->authenticationFailed(new ClientAuthenticationFailedEventArgs($this->esConnection, $reason));
    }

    public function whenConnected(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenConnected($handler);
    }

    public function whenDisconnected(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenDisconnected($handler);
    }

    public function whenReconnecting(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenReconnecting($handler);
    }

    public function whenClosed(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenClosed($handler);
    }

    public function whenErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenErrorOccurred($handler);
    }

    public function whenAuthenticationFailed(callable $handler): ListenerHandler
    {
        return $this->eventHandler->whenAuthenticationFailed($handler);
    }

    public function detach(ListenerHandler $handler): void
    {
        $this->eventHandler->detach($handler);
    }

    private function compareWasConnected(bool $value, bool $comparand): bool
    {
        $original = $this->wasConnected;

        if ($this->wasConnected === $comparand) {
            $this->wasConnected = $value;
        }

        return $original;
    }
}
