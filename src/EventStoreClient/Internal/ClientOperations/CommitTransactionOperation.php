<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStoreClient\Data\Position;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Data\WriteResult;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionCommit;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionCommitCompleted;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

/** @internal */
class CommitTransactionOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var int */
    private $transactionId;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        int $transactionId,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->transactionId = $transactionId;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::transactionCommit(),
            TcpCommand::transactionCommitCompleted(),
            TransactionCommitCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new TransactionCommit();
        $message->setRequireMaster($this->requireMaster);
        $message->setTransactionId($this->transactionId);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var TransactionCommitCompleted $response */
        switch ($response->getResult()) {
            case OperationResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case OperationResult::PrepareTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'PrepareTimeout');
            case OperationResult::ForwardTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'ForwardTimeout');
            case OperationResult::CommitTimeout:
                return new InspectionResult(InspectionDecision::retry(), 'CommitTimeout');
            case OperationResult::WrongExpectedVersion:
                $exception = new WrongExpectedVersion(\sprintf(
                    'Commit transaction failed due to WrongExpectedVersion. Transaction id: \'%s\'',
                    $this->transactionId
                ));
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'WrongExpectedVersion');
            case OperationResult::StreamDeleted:
                $this->fail(new StreamDeleted());

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case OperationResult::InvalidTransaction:
                $this->fail(new InvalidTransaction());

                return new InspectionResult(InspectionDecision::endOperation(), 'InvalidTransaction');
            case OperationResult::AccessDenied:
                $exception = new AccessDenied('Write access denied');
                $this->fail($exception);

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response)
    {
        /** @var TransactionCommitCompleted $response */
        return new WriteResult(
            $response->getLastEventNumber(),
            new Position(
                $response->getCommitPosition() ?? -1,
                $response->getPreparePosition() ?? -1
            )
        );
    }
}
