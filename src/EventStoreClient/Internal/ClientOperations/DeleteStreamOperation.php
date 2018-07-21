<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\DeleteResult;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\DeleteStream;
use Prooph\EventStore\Messages\DeleteStreamCompleted;
use Prooph\EventStore\Messages\OperationResult;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;

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
                $exception = WrongExpectedVersion::withExpectedVersion($this->stream, $this->expectedVersion);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $exception = StreamDeleted::with($this->stream);
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $exception = new InvalidTransaction();
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = AccessDenied::toStream($this->stream);
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
