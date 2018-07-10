<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\ByteStream\ClosedException;
use Amp\Promise;
use Amp\TimeoutException;
use Generator;
use Prooph\EventStore\Exception\ConnectionClosedException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Exception\OperationTimedOutException;
use Prooph\EventStoreClient\Exception\RetriesLimitReachedException;
use function Amp\call;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;

/** @internal */
class OperationsManager
{
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var OperationItem[] */
    private $activeOperations = [];
    /** @var TcpDispatcher */
    private $dispatcher;

    public function __construct(string $connectionName, ConnectionSettings $settings)
    {
        $this->connectionName = $connectionName;
        $this->settings = $settings;
    }

    public function getActiveOperation(string $correlationId): ?OperationItem
    {
        return $this->activeOperations[$correlationId] ?? null;
    }

    public function cleanUp(): void
    {
        $closedConnectionException = ConnectionClosedException::withName($this->connectionName);

        foreach ($this->activeOperations as $operationItem) {
            $operationItem->operation()->fail($closedConnectionException);
        }

        $this->activeOperations = [];
    }

    public function totalOperationCount(): int
    {
        return \count($this->activeOperations);
    }

    public function executeOperation(OperationItem $operation, TcpPackageConnection $connection)
    {
        if (null === $this->dispatcher) {
            throw new InvalidOperationException('Failed connection');
        }

        $operation->setLastUpdated(DateTimeUtil::utcNow());
        $correlationId = $operation->correlationId();
        $this->activeOperations[$correlationId] = $operation;

        return call(function() use ($operation, $connection, $correlationId): Generator {
            $package = $operation->operation()->createNetworkPackage($correlationId);

            try {
                $promise = yield $connection->sendAsync($package);
            } catch (ClosedException $e) {
                $operation->operation()->fail(ConnectionClosedException::withName($this->connectionName));
            }

            try {
                yield Promise\timeout($promise, $this->settings->operationTimeoutCheckPeriod());
            } catch (TimeoutException $e) {
                if ($operation->maxRetries() >= $this->settings->maxRetries()) {
                    $operation->operation()->fail(
                        RetriesLimitReachedException::with($operation->operation(), $this->settings->maxRetries())
                    );
                }

                $timeout = $operation->timeout()->format('U.u');
                $check = DateTimeUtil::utcNow()->format('U.u') + ($this->settings->operationTimeout() / 1000);

                if ($timeout > $check) {
                    $operation->incRetryCount();

                    return $this->executeOperation($operation);
                } else {
                    $operation->operation()->fail(
                        OperationTimedOutException::with($this->connectionName, $operation->operation())
                    );
                }
            }
        });
    }

    public function removeOperation(OperationItem $operation): void
    {
        unset($this->activeOperations[$operation->correlationId()]);
    }
}
