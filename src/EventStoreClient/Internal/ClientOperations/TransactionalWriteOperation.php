<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Data\EventData;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\OperationResult;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWrite;
use Prooph\EventStoreClient\Messages\ClientMessages\TransactionWriteCompleted;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

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
                $exception = new AccessDeniedException('Write access denied');
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
