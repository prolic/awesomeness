<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Data\ReadDirection;
use Prooph\EventStoreClient\Data\ResolvedEvent;
use Prooph\EventStoreClient\Data\SliceReadStatus;
use Prooph\EventStoreClient\Data\StreamEventsSlice;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\SystemData\InspectionDecision;
use Prooph\EventStoreClient\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEvents;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\ReadStreamEventsCompleted\ReadStreamResult;
use Prooph\EventStoreClient\Messages\ClientMessages\ResolvedIndexedEvent;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class ReadStreamEventsBackwardOperation extends AbstractOperation
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
        Logger $logger,
        Deferred $deferred,
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
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::readStreamEventsBackward(),
            TcpCommand::readStreamEventsBackwardCompleted(),
            ReadStreamEventsCompleted::class
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

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var ReadStreamEventsCompleted $response */
        switch ($response->getResult()) {
            case ReadStreamResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case ReadStreamResult::StreamDeleted:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'StreamDeleted');
            case ReadStreamResult::NoStream:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'NoStream');
            case ReadStreamResult::Error:
                $this->fail(new ServerError($response->getError()));

                return new InspectionResult(InspectionDecision::endOperation(), 'Error');
            case ReadStreamResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            default:
                throw new ServerError('Unexpected ReadStreamResult');
        }
    }

    protected function transformResponse(Message $response)
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
            ReadDirection::backward(),
            $resolvedEvents,
            $response->getNextEventNumber(),
            $response->getLastEventNumber(),
            $response->getIsEndOfStream()
        );
    }

    public function name(): string
    {
        return 'ReadStreamEventsBackward';
    }
}
