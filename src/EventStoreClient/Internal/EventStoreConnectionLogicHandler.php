<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\Deferred;
use Prooph\EventStore\Exception\ConnectionClosedException;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\NodeEndPoints;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Event\ClientAuthenticationFailedEventArgs;
use Prooph\EventStoreClient\Event\ClientClosedEventArgs;
use Prooph\EventStoreClient\Event\ClientConnectionEventArgs;
use Prooph\EventStoreClient\Event\ClientErrorEventArgs;
use Prooph\EventStoreClient\Event\ClientReconnectingEventArgs;
use Prooph\EventStoreClient\EventHandler;
use Prooph\EventStoreClient\EventStoreAsyncConnection;
use Prooph\EventStoreClient\Exception\CannotEstablishConnectionException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Internal\Message\CloseConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\EstablishTcpConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\HandleTcpPackageMessage;
use Prooph\EventStoreClient\Internal\Message\StartConnectionMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionClosedMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionErrorMessage;
use Prooph\EventStoreClient\Internal\Message\TcpConnectionEstablishedMessage;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class EventStoreConnectionLogicHandler
{
    /** @var EventStoreAsyncConnection */
    private $esConnection;
    /** @var TcpPackageConnection */
    private $connection;
    /** @var ConnectionSettings */
    private $settings;
    /** @var ConnectionState */
    private $state;
    /** @var ConnectingPhase */
    private $connectionPhase;
    /** @var EndPointDiscoverer */
    private $endPointDiscoverer;
    /** @var MessageHandler */
    private $handler;
    /** @var OperationsManager */
    private $manager;
    /** @var EventHandler */
    private $eventHandler;

    public function __construct(EventStoreAsyncConnection $connection, ConnectionSettings $settings)
    {
        $this->esConnection = $connection;
        $this->settings = $settings;
        $this->state = ConnectionState::init();
        $this->connectingPhase = ConnectingPhase::invalid();
        $this->handler = new MessageHandler();
        $this->manager = new OperationsManager($connection->connectionName(), $settings);
        $this->eventHandler = new EventHandler();

        $this->handler->registerHandler(
            StartConnectionMessage::class,
            [$this, 'startConnection']
        );
        $this->handler->registerHandler(
            CloseConnectionMessage::class,
            [$this, 'closeConnection']
        );
        $this->handler->registerHandler(
            EstablishTcpConnectionMessage::class,
            [$this, 'establishTcpConnection']
        );
    }

    public function totalOperationCount(): int
    {
        return $this->manager ? $this->manager->totalOperationCount() : 0;
    }

    public function enqueueMessage(Message $message): void
    {
        $this->handler->handle($message);
    }

    /** @throws \Exception */
    public function startConnection(StartConnectionMessage $message): void
    {
        switch ($this->state->value()) {
            case ConnectionState::Init:
                $this->endPointDiscoverer = $message->endPointDiscoverer();
                $this->state = ConnectionState::connecting();
                $this->connectingPhase = ConnectingPhase::reconnecting();
                $this->discoverEndPoint($message->deferred());
                break;
            case ConnectionState::Connecting:
            case ConnectionState::Connected:
                $message->deferred()->fail(new InvalidOperationException(\sprintf(
                    'EventStoreConnection \'%s\' is already active',
                    $this->esConnection->connectionName()
                )));
                break;
            case ConnectionState::Closed:
                $message->deferred()->fail(ConnectionClosedException::withName($this->esConnection->connectionName()));
                break;
            default:
                throw new \Exception(\sprintf(
                    'Unknown state: %s',
                    $this->state->name()
                ));
        }
    }

    public function closeConnection(CloseConnectionMessage $message): void
    {
        if ($this->state->equals(ConnectionState::closed())) {
            // ignore
            return;
        }

        $this->state = ConnectionState::closed();

        $this->manager->cleanUp();
        //_subscriptions.CleanUp();
        $this->connection->close();

        if (null !== $message->exception()) {
            $this->raiseErrorOccurred($message->exception());
        }

        $this->raiseClosed($message->reason());
    }

    public function establishTcpConnection(EstablishTcpConnectionMessage $message): void
    {
        $endPoints = $message->nodeEndPoints();

        $endPoint = $this->settings->useSslConnection()
            ? $endPoints->secureTcpEndPoint() ?? $endPoints->tcpEndPoint()
            : $endPoints->tcpEndPoint();

        if (null === $endPoint) {
            $this->closeConnection(new CloseConnectionMessage('No end point to node specified'));

            return;
        }

        if ($this->state->equals(ConnectionState::connecting())) {
            return;
        }

        if (! $this->connectingPhase->equals(ConnectingPhase::endPointDiscovery())) {
            return;
        }

        $this->connectingPhase = ConnectingPhase::connectionEstablishing();

        $this->connection = new TcpPackageConnection(
            $endPoint,
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

        $this->connection->startReceiving();
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

    private function discoverEndPoint(Deferred $deferred): void
    {
        if (! $this->state->equals(ConnectionState::connecting())) {
            return;
        }

        if (! $this->connectionPhase->equals(ConnectingPhase::reconnecting())) {
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

                $deferred->fail(new CannotEstablishConnectionException('Cannot resolve target end point'));

                return;
            }

            $this->enqueueMessage(new EstablishTcpConnectionMessage($endpoints));
            $deferred->resolve(null);
        });
    }
}
