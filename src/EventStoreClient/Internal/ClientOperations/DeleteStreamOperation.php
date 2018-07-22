<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Data\DeleteResult;
use Prooph\EventStoreClient\Data\Position;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\InvalidTransactionException;
use Prooph\EventStoreClient\Exception\StreamDeletedException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Exception\WrongExpectedVersionException;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\DeleteStream;
use Prooph\EventStoreClient\Messages\ClientMessages\DeleteStreamCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

/** @internal */
class DeleteStreamOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var bool */
    private $hardDelete;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->hardDelete = $hardDelete;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::deleteStream(),
            TcpCommand::deleteStreamCompleted(),
            DeleteStreamCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new DeleteStream();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setHardDelete($this->hardDelete);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    public function inspectResponse(Message $response): InspectionResult
    {
        /** @var DeleteStreamCompleted $response */

        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'PrepareTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'CommitTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'ForwardTimeout');
            case OperationResult::WrongExpectedVersion:
                $exception = WrongExpectedVersionException::withExpectedVersion($this->stream, $this->expectedVersion);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $exception = StreamDeletedException::with($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $exception = new InvalidTransactionException();
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = AccessDeniedException::toStream($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response)
    {
        /** @var DeleteStreamCompleted $response */
        return new DeleteResult(new Position(
            $response->getCommitPosition() ?? -1,
            $response->getCommitPosition() ?? -1)
        );
    }
}
