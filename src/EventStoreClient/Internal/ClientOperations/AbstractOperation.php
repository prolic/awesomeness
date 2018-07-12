<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Amp\Promise;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\IpEndPoint;
use Prooph\EventStore\Messages\NotHandled;
use Prooph\EventStore\Messages\NotHandled_MasterInfo;
use Prooph\EventStore\Messages\NotHandled_NotHandledReason;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Exception\NotAuthenticatedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Exception\UnexpectedCommandException;
use Prooph\EventStoreClient\Internal\ClientOperation;
use Throwable;

/** @internal */
abstract class AbstractOperation implements ClientOperation
{
    /** @var Deferred */
    private $deferred;
    /** @var UserCredentials|null */
    private $credentials;
    /** @var TcpCommand */
    private $requestCommand;
    /** @var TcpCommand */
    private $responseCommand;

    public function __construct(
        Deferred $deferred,
        ?UserCredentials $credentials,
        TcpCommand $requestCommand,
        TcpCommand $responseCommand
    ) {
        $this->deferred = $deferred;
        $this->credentials = $credentials;
        $this->requestCommand = $requestCommand;
        $this->responseCommand = $responseCommand;
    }

    abstract protected function createRequestDto(): Message;

    abstract protected function inspectResponse(Message $response): InspectionResult;

    abstract protected function transformResponse(Message $response): object;

    public function promise(): Promise
    {
        return $this->deferred->promise();
    }

    public function createNetworkPackage(string $correlationId): TcpPackage
    {
        return new TcpPackage(
            $this->requestCommand,
            $this->credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $this->createRequestDto(),
            $this->credentials ?? null
        );
    }

    public function inspectPackage(TcpPackage $package): InspectionResult
    {
        if ($package->command()->equals($this->responseCommand)) {
            return $this->inspectResponse($package->data());
        }

        switch ($package->command()->value()) {
            case TcpCommand::NotAuthenticatedException:
                return $this->inspectNotAuthenticated($package);
            case TcpCommand::BadRequest:
                return $this->inspectBadRequest($package);
            case TcpCommand::NotHandled:
                return $this->inspectNotHandled($package);
            default:
                return $this->inspectUnexpectedCommand($package, $this->responseCommand);
        }
    }

    protected function succeed(Message $response): void
    {
        try {
            $result = $this->transformResponse($response);
        } catch (Throwable $e) {
            $this->deferred->fail($e);

            return;
        }

        $this->deferred->resolve($result);
    }

    public function fail(Throwable $exception): void
    {
        $this->deferred->fail($exception);
    }

    private function inspectNotAuthenticated(TcpPackage $package): InspectionResult
    {
        $this->fail(new NotAuthenticatedException());

        return new InspectionResult(InspectionDecision::endOperation(), 'Not authenticated');
    }

    private function inspectBadRequest(TcpPackage $package): InspectionResult
    {
        $this->fail(new ServerError());

        return new InspectionResult(InspectionDecision::endOperation(), 'Bad request');
    }

    private function inspectNotHandled(TcpPackage $package): InspectionResult
    {
        /** @var NotHandled $message */
        $message = $package->data();

        switch ($message->getReason()) {
            case NotHandled_NotHandledReason::NotReady:
                return new InspectionResult(InspectionDecision::retry(), 'Not handled: not ready');
            case NotHandled_NotHandledReason::TooBusy:
                return new InspectionResult(InspectionDecision::retry(), 'Not handled: too busy');
            case NotHandled_NotHandledReason::NotMaster:
                /** @var NotHandled_MasterInfo $masterInfo */
                $masterInfo = $message->getAdditionalInfo();

                return new InspectionResult(
                    InspectionDecision::reconnect(),
                    'Not handled: not master',
                    new IpEndPoint(
                        $masterInfo->getExternalTcpAddress(),
                        $masterInfo->getExternalTcpPort()
                    ),
                    new IpEndPoint(
                        $masterInfo->getExternalSecureTcpAddress(),
                        $masterInfo->getExternalSecureTcpPort()
                    )
                );
            default:
                return new InspectionResult(InspectionDecision::retry(), 'Not handled: unknown');
        }
    }

    private function inspectUnexpectedCommand(TcpPackage $package, TcpCommand $expectedCommand): InspectionResult
    {
        $exception = UnexpectedCommandException::with($expectedCommand->name(), $package->command()->name());
        $this->fail($exception);

        return new InspectionResult(InspectionDecision::endOperation(), $exception->getMessage());
    }
}
