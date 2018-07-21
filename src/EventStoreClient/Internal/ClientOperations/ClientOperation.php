<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Promise;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackage;
use Throwable;

/** @internal */
interface ClientOperation
{
    public function promise(): Promise;

    public function createNetworkPackage(string $correlationId): TcpPackage;

    public function inspectPackage(TcpPackage $package): InspectionResult;

    public function fail(Throwable $exception): void;
}
