<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Amp\ByteStream\ClosedException;
use Amp\Promise;
use Generator;
use Prooph\EventStore\Exception\ConnectionClosedException;
use Prooph\EventStore\Internal\DateTimeUtil;
use Prooph\EventStoreClient\ConnectionSettings;
use Prooph\EventStoreClient\Exception\OperationTimedOutException;
use Prooph\EventStoreClient\Exception\RetriesLimitReachedException;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackageConnection;
use function Amp\call;

/** @internal */
class OperationsManager
{
    /** @var callable */
    private $operationItemSeqNoComparer;
    /** @var string */
    private $connectionName;
    /** @var ConnectionSettings */
    private $settings;
    /** @var OperationItem[] */
    private $activeOperations = [];
    /** @var OperationItem[] */
    private $waitingOperations = [];
    /** @var OperationItem[] */
    private $retryPendingOperations = [];
    /** @var int */
    private $totalOperationCount = 0;

    public function __construct(string $connectionName, ConnectionSettings $settings)
    {
        $this->connectionName = $connectionName;
        $this->settings = $settings;

        $this->operationItemSeqNoComparer = function (OperationItem $a, OperationItem $b): int {
            if ($a->segNo() === $b->segNo()) {
                return 0;
            }

            return ($a->segNo() < $b->segNo()) ? -1 : 1;
        };
    }

    public function totalOperationCount(): int
    {
        return $this->totalOperationCount;
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

        foreach ($this->waitingOperations as $operationItem) {
            $operationItem->operation()->fail($closedConnectionException);
        }

        foreach ($this->retryPendingOperations as $operationItem) {
            $operationItem->operation()->fail($closedConnectionException);
        }

        $this->activeOperations = [];
        $this->waitingOperations = [];
        $this->retryPendingOperations = [];
        $this->totalOperationCount = 0;
    }

    public function checkTimeoutsAndRetry(TcpPackageConnection $connection): void
    {
        $retryOperations = [];
        $removeOperations = [];

        foreach ($this->activeOperations as $operation) {
            if ($operation->connectionId() !== $connection->connectionId()) {
                $retryOperations[] = $operation;
            } elseif ($operation->timeout() > 0
                && DateTimeUtil::utcNow()->format('U.u') - $operation->lastUpdated()->format('U.u') > $this->settings->operationTimeout()
            ) {
                if ($this->settings->failOnNoServerResponse()) {
                    $operation->operation()->fail(
                        OperationTimedOutException::with($this->connectionName, $operation->operation())
                    );
                    $removeOperations[] = $operation;
                } else {
                    $retryOperations[] = $operation;
                }
            }
        }

        foreach ($retryOperations as $operation) {
            $this->scheduleOperationRetry($operation);
        }

        foreach ($removeOperations as $operation) {
            $this->removeOperation($operation);
        }

        if (\count($this->retryPendingOperations) > 0) {
            \usort($this->retryPendingOperations, $this->operationItemSeqNoComparer);

            foreach ($this->retryPendingOperations as $operation) {
                $operation->setCorrelationId(CorrelationIdGenerator::generate());
                $operation->incRetryCount();
                $this->scheduleOperation($operation, $connection);
            }
        }

        $this->tryScheduleWaitingOperations($connection);
    }

    public function scheduleOperationRetry(OperationItem $operation): void
    {
        if (! $this->removeOperation($operation)) {
            return;
        }

        if ($operation->maxRetries() >= 0 && $operation->retryCount() >= $operation->maxRetries()) {
            $operation->operation()->fail(
                RetriesLimitReachedException::with($operation->operation(), $operation->retryCount())
            );

            return;
        }

        $this->retryPendingOperations[] = $operation;
    }

    public function removeOperation(OperationItem $operation): bool
    {
        if (! isset($this->activeOperations[$operation->correlationId()])) {
            return false;
        }

        unset($this->activeOperations[$operation->correlationId()]);

        return true;
    }

    public function tryScheduleWaitingOperations(TcpPackageConnection $connection): void
    {
        while (\count($this->waitingOperations) > 0
            && \count($this->activeOperations) < $this->settings->maxConcurrentItems()
        ) {
            $this->executeOperation(\array_shift($this->waitingOperations), $connection);
        }

        $this->totalOperationCount = \count($this->activeOperations) + \count($this->waitingOperations);
    }

    public function executeOperation(OperationItem $operation, TcpPackageConnection $connection): Promise
    {
        $operation->setConnectionId($connection->connectionId());
        $operation->setLastUpdated(DateTimeUtil::utcNow());

        $correlationId = $operation->correlationId();
        $this->activeOperations[$correlationId] = $operation;

        return call(function () use ($operation, $connection, $correlationId): Generator {
            $package = $operation->operation()->createNetworkPackage($correlationId);

            try {
                yield $connection->sendAsync($package);
            } catch (ClosedException $e) {
                $operation->operation()->fail(ConnectionClosedException::withName($this->connectionName));
            }
        });
    }

    public function enqueueOperation(OperationItem $operationItem): void
    {
        $this->waitingOperations[] = $operationItem;
    }

    public function scheduleOperation(OperationItem $operation, TcpPackageConnection $connection): void
    {
        $this->waitingOperations[] = $operation;
        $this->tryScheduleWaitingOperations($connection);
    }
}
