<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Data\EventReadResult;
use Prooph\EventStoreClient\Data\EventReadStatus;
use Prooph\EventStoreClient\Data\ResolvedEvent;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEvent;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadEventCompleted\ReadEventResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

/** @internal */
class ReadEventOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $eventNumber;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        Deferred $deferred,
        bool $requireMaster,
        string $stream,
        int $eventNumber,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->eventNumber = $eventNumber;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::readEvent(),
            TcpCommand::readEventCompleted(),
            ReadEventCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadEvent();
        $message->setEventStreamId($this->stream);
        $message->setEventNumber($this->eventNumber);
        $message->setResolveLinkTos($this->resolveLinkTos);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var ReadEventCompleted $response */

        switch ($response->getResult()) {
            case ReadEventResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadEventResult::NotFound:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NotFound');
            case ReadEventResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NoStream');
            case ReadEventResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case ReadEventResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadEventResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    protected function transformResponse(Message $response)
    {
        /* @var ReadEventCompleted $response */
        $eventMessage = $response->getEvent();
        $event = null;
        $link = null;

        if ($eventMessage->getEvent()) {
            $event = EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getEvent());
        }

        if ($link = $eventMessage->getLink()) {
            $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
        }

        $resolvedEvent = new ResolvedEvent($event, $link, null);

        return new EventReadResult(
            EventReadStatus::byValue($response->getResult()),
            $this->stream,
            $this->eventNumber,
            $resolvedEvent
        );
    }
}
