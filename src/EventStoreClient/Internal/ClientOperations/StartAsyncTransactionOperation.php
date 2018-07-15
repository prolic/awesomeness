<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\EventStoreAsyncTransaction;
use Prooph\EventStore\EventStoreAsyncTransactionConnection;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\OperationResult;
use Prooph\EventStore\Messages\TransactionStart;
use Prooph\EventStore\Messages\TransactionStartCompleted;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;

/** @internal */
class StartAsyncTransactionOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var EventStoreAsyncTransactionConnection */
    protected $parentConnection;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        EventStoreAsyncTransactionConnection $parentConnection,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->parentConnection = $parentConnection;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::transactionStart(),
            TcpCommand::transactionStartCompleted()
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new TransactionStart();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var TransactionStartCompleted $response */
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
                $exception = new WrongExpectedVersion(\sprintf(
                    'Start transaction failed due to WrongExpectedVersion. Stream: \'%s\', Expected version: \'%s\'',
                    $this->stream,
                    $this->expectedVersion
                ));
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $this->fail(StreamDeleted::with($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransaction());

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response)
    {
        /** @var TransactionStartCompleted $response */
        return new EventStoreAsyncTransaction(
            $response->getTransactionId(),
            $this->credentials,
            $this->parentConnection
        );
    }
}
