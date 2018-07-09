<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

use Prooph\EventStoreClient\Event\ClientAuthenticationFailedEventArgs;
use Prooph\EventStoreClient\Event\ClientClosedEventArgs;
use Prooph\EventStoreClient\Event\ClientConnectionEventArgs;
use Prooph\EventStoreClient\Event\ClientErrorEventArgs;
use Prooph\EventStoreClient\Event\ClientReconnectingEventArgs;
use Prooph\EventStoreClient\Event\ListenerHandler;
use SplObjectStorage;

class EventHandler
{
    /** @var SplObjectStorage[] */
    private $handlers;

    public function __construct()
    {
        $this->handlers = [
            'connected' => new SplObjectStorage(),
            'disconnected' => new SplObjectStorage(),
            'reconnecting' => new SplObjectStorage(),
            'closed' => new SplObjectStorage(),
            'errorOccurred' => new SplObjectStorage(),
            'authenticationFailed' => new SplObjectStorage(),
        ];
    }

    public function connected(ClientConnectionEventArgs $args): void
    {
        foreach ($this->handlers['connected'] as $handler) {
            $handler->callback()();
        }
    }

    public function disconnected(ClientConnectionEventArgs $args): void
    {
        foreach ($this->handlers['disconnected'] as $handler) {
            $handler->callback()();
        }
    }

    public function reconnecting(ClientReconnectingEventArgs $args): void
    {
        foreach ($this->handlers['reconnecting'] as $handler) {
            $handler->callback()();
        }
    }

    public function closed(ClientClosedEventArgs $args): void
    {
        foreach ($this->handlers['closed'] as $handler) {
            $handler->callback()();
        }
    }

    public function errorOccurred(ClientErrorEventArgs $args): void
    {
        foreach ($this->handlers['errorOccurred'] as $handler) {
            $handler->callback()();
        }
    }

    public function authenticationFailed(ClientAuthenticationFailedEventArgs $args): void
    {
        foreach ($this->handlers['authenticationFailed'] as $handler) {
            $handler->callback()();
        }
    }

    public function whenConnected(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'connected');
    }

    public function whenDisconnected(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'disconnected');
    }

    public function whenReconnecting(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'reconnecting');
    }

    public function whenClosed(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'closed');
    }

    public function whenErrorOccurred(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'errorOccurred');
    }

    public function whenAuthenticationFailed(callable $handler): ListenerHandler
    {
        return $this->attach($handler, 'authenticationFailed');
    }

    public function detach(ListenerHandler $handler): void
    {
        foreach ($this->handlers as $storage) {
            $storage->detach($handler);
        }
    }

    private function attach(callable $handler, string $eventName): ListenerHandler
    {
        $handler = new ListenerHandler($handler);

        $this->handlers[$eventName]->attach($handler);

        return $handler;
    }
}