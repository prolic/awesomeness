<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\OperationResult;
use Prooph\EventStore\Messages\TransactionWrite;
use Prooph\EventStore\Messages\TransactionWriteCompleted;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\NewEventConverter;

/** @internal */
class TransactionalWriteOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var int */
    private $transactionId;
    /** @var EventData[] */
    private $events;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        int $transactionId,
        array $events,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->transactionId = $transactionId;
        $this->events = $events;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::transactionWrite(),
            TcpCommand::transactionWriteCompleted(),
            TransactionWriteCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        foreach ($this->events as $event) {
            $dtos[] = NewEventConverter::convert($event);
        }

        $message = new TransactionWrite();
        $message->setRequireMaster($this->requireMaster);
        $message->setTransactionId($this->transactionId);
        $message->setEvents($dtos);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var TransactionWriteCompleted $response */
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
    }
}
