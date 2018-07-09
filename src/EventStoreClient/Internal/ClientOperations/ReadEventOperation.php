<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\EventReadStatus;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Messages\ReadEvent;
use Prooph\EventStore\Messages\ReadEventCompleted;
use Prooph\EventStore\Messages\ReadEventCompleted_ReadEventResult;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;

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
        TcpDispatcher $dispatcher,
        ReadBuffer $readBuffer,
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
            $dispatcher,
            $readBuffer,
            $userCredentials,
            TcpCommand::readEvent(),
            TcpCommand::readEventCompleted()
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

    protected function inspectResponse(Message $response): void
    {
        /** @var ReadEventCompleted $response */

        switch ($response->getResult()) {
            case ReadEventCompleted_ReadEventResult::Success:
            case ReadEventCompleted_ReadEventResult::NotFound:
            case ReadEventCompleted_ReadEventResult::NoStream:
            case ReadEventCompleted_ReadEventResult::StreamDeleted:
                return;
            case ReadEventCompleted_ReadEventResult::Error:
                throw new ServerError($response->getError());
            case ReadEventCompleted_ReadEventResult::AccessDenied:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    protected function transformResponse(Message $response): object
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
