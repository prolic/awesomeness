<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Promise;
use Generator;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Internal\Messages\NotHandled;
use Prooph\EventStore\Internal\Messages\NotHandled_NotHandledReason;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Exception\NotAuthenticatedException;
use Prooph\EventStoreClient\Exception\NotHandledException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Exception\UnexpectedCommandException;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use function Amp\call;

/** @internal */
abstract class AbstractOperation
{
    /** @var TcpDispatcher */
    private $dispatcher;
    /** @var ReadBuffer */
    private $readBuffer;
    /** @var UserCredentials|null */
    private $credentials;
    /** @var TcpCommand */
    private $requestCommand;
    /** @var TcpCommand */
    private $responseCommand;

    public function __construct(
        TcpDispatcher $dispatcher,
        ReadBuffer $readBuffer,
        ?UserCredentials $credentials,
        TcpCommand $requestCommand,
        TcpCommand $responseCommand
    ) {
        $this->dispatcher = $dispatcher;
        $this->readBuffer = $readBuffer;
        $this->credentials = $credentials;
        $this->requestCommand = $requestCommand;
        $this->responseCommand = $responseCommand;
    }

    abstract protected function createRequestDto(): Message;

    abstract protected function inspectResponse(Message $response): void;

    abstract protected function transformResponse(Message $response): object;

    protected function createNetworkPackage(string $correlationId): TcpPackage
    {
        return new TcpPackage(
            $this->requestCommand,
            $this->credentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $correlationId,
            $this->createRequestDto(),
            $this->credentials ?? null
        );
    }

    /**
     * @return Promise<object>
     */
    public function __invoke(): Promise
    {
        return call(function (): Generator {
            $correlationId = $this->dispatcher->createCorrelationId();

            $request = $this->createNetworkPackage($correlationId);

            yield $this->dispatcher->dispatch($request);

            $response = yield $this->readBuffer->waitFor($correlationId);

            $this->inspectPackage($response);

            return $this->transformResponse($response);
        });
    }

    protected function inspectPackage(TcpPackage $package): InspectionResult
    {
        if ($package->command()->equals($this->responseCommand)) {
            $this->inspectResponse($package->data());
        }

        switch ($package->command()->value()) {
            case TcpCommand::NotAuthenticated:
                throw new NotAuthenticatedException();
            case TcpCommand::BadRequest:
                throw new ServerError();
            case TcpCommand::NotHandled:
                /** @var NotHandled $message */
                $message = $package->data();
                switch ($message->getReason()) {
                    case NotHandled_NotHandledReason::NotReady:
                        throw NotHandledException::notReady();
                    case NotHandled_NotHandledReason::TooBusy:
                        throw NotHandledException::tooBusy();
                    case NotHandled_NotHandledReason::NotMaster:
                        throw NotHandledException::notMaster();
                    default:
                        throw new NotHandledException('Not handled: unknown reason');
                }
                // no break
            default:
                throw new UnexpectedCommandException();
        }
    }
}
