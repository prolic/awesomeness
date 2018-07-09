<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\ReadDirection;
use Prooph\EventStore\Data\ResolvedEvent;
use Prooph\EventStore\Data\SliceReadStatus;
use Prooph\EventStore\Data\StreamEventsSlice;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\Messages\ReadStreamEvents;
use Prooph\EventStore\Internal\Messages\ReadStreamEventsCompleted;
use Prooph\EventStore\Internal\Messages\ReadStreamEventsCompleted_ReadStreamResult;
use Prooph\EventStore\Internal\Messages\ResolvedIndexedEvent;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;

/** @internal */
class ReadStreamEventsForwardOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $start;
    /** @var int */
    private $count;
    /** @var bool */
    private $resolveLinkTos;

    public function __construct(
        TcpDispatcher $dispatcher,
        ReadBuffer $readBuffer,
        bool $requireMaster,
        string $stream,
        int $start,
        int $count,
        bool $resolveLinkTos,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->start = $start;
        $this->count = $count;
        $this->resolveLinkTos = $resolveLinkTos;

        parent::__construct(
            $dispatcher,
            $readBuffer,
            $userCredentials,
            TcpCommand::readStreamEventsForward(),
            TcpCommand::readStreamEventsForwardCompleted()
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new ReadStreamEvents();
        $message->setRequireMaster($this->requireMaster);
        $message->setEventStreamId($this->stream);
        $message->setFromEventNumber($this->start);
        $message->setMaxCount($this->count);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return $message;
    }

    protected function inspectResponse(Message $response): void
    {
        /** @var ReadStreamEventsCompleted $response */

        switch ($response->getResult()) {
            case ReadStreamEventsCompleted_ReadStreamResult::Success:
            case ReadStreamEventsCompleted_ReadStreamResult::NoStream:
            case ReadStreamEventsCompleted_ReadStreamResult::StreamDeleted:
                return;
            case ReadStreamEventsCompleted_ReadStreamResult::Error:
                throw new ServerError($response->getError());
            case ReadStreamEventsCompleted_ReadStreamResult::AccessDenied:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new ServerError('Unexpected ReadStreamResult');
        }
    }

    protected function transformResponse(Message $response): object
    {
        /* @var ReadStreamEventsCompleted $response */

        $records = $response->getEvents();

        $resolvedEvents = [];

        foreach ($records as $record) {
            /** @var ResolvedIndexedEvent $record */
            $event = EventMessageConverter::convertEventRecordMessageToEventRecord($record->getEvent());
            $link = null;

            if ($link = $record->getLink()) {
                $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
            }

            $resolvedEvents[] = new ResolvedEvent($event, $link, null);
        }

        return new StreamEventsSlice(
            SliceReadStatus::byValue($response->getResult()),
            $this->stream,
            $this->start,
            ReadDirection::forward(),
            $resolvedEvents,
            $response->getNextEventNumber(),
            $response->getLastEventNumber(),
            $response->getIsEndOfStream()
        );
    }
}
