<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Promise;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpPackage;
use Throwable;

/** @internal */
interface ClientOperation
{
    public function promise(): Promise;

    public function createNetworkPackage(string $correlationId): TcpPackage;

    public function inspectPackage(TcpPackage $package): InspectionResult;

    public function fail(Throwable $exception): void;

    public function name(): string;
}
