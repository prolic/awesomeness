<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\ReadAllEvents;
use Prooph\EventStore\Messages\ReadAllEventsCompleted;
use Prooph\EventStore\Messages\ReadAllEventsCompleted_ReadAllResult;
use Prooph\EventStore\Messages\ResolvedIndexedEvent;
use Prooph\EventStoreClient\Data\AllEventsSlice;
use Prooph\EventStoreClient\Data\Position;
use Prooph\EventStoreClient\Data\ReadDirection;
use Prooph\EventStoreClient\Data\ResolvedEvent;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

/** @internal */
class ReadAllEventsForwardOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var Position */
    private $position;
    /** @var int */
    private $maxCount;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        Position $position,
        int $maxCount,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->position = $position;
        $this->maxCount = $maxCount;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::readAllEventsForward(),
            TcpCommand::readAllEventsForwardCompleted(),
            ReadAllEventsCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadAllEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setCommitPosition($this->position->commitPosition());
        $message->setPreparePosition($this->position->preparePosition());
        $message->setMaxCount($this->maxCount);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var ReadAllEventsCompleted $response */
        switch ($response->getResult()) {
            case ReadAllEventsCompleted_ReadAllResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadAllEventsCompleted_ReadAllResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadAllEventsCompleted_ReadAllResult::AccessDenied:
                $this->fail(AccessDenied::toAllStream());

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadAllResult');
        }
    }

    protected function transformResponse(Message $response)
    {
        /* @var ReadAllEventsCompleted $response */
        $records = $response->getEvents();

        $resolvedEvents = [];

        foreach ($records as $record) {
            /** @var ResolvedIndexedEvent $record */
            $event = EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent());
            $link = null;

            if ($link = $record->getLink()) {
                $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
            }

            $resolvedEvents[] = new ResolvedEvent($event, $link, new Position($response->getCommitPosition(), $response->getPreparePosition()));
        }

        return new AllEventsSlice(
            ReadDirection::forward(),
            new Position($response->getCommitPosition(), $response->getPreparePosition()),
            new Position($response->getNextCommitPosition(), $response->getNextPreparePosition()),
            $resolvedEvents
        );
    }
}
