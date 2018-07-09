<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\EventData;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Data\WriteResult;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Internal\Messages\OperationResult;
use Prooph\EventStore\Internal\Messages\WriteEvents;
use Prooph\EventStore\Internal\Messages\WriteEventsCompleted;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\NewEventConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;

/** @internal */
class AppendToStreamOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var EventData[] */
    private $events;

    public function __construct(
        TcpDispatcher $dispatcher,
        ReadBuffer $readBuffer,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        array $events,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->events = $events;

        parent::__construct(
            $dispatcher,
            $readBuffer,
            $userCredentials,
            TcpCommand::writeEvents(),
            TcpCommand::writeEventsCompleted()
        );
    }

    protected function createRequestDto(): Message
    {
        foreach ($this->events as $event) {
            $dtos[] = NewEventConverter::convert($event);
        }

        $message = new WriteEvents();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setEvents($dtos);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    protected function inspectResponse(Message $response): void
    {
        /** @var WriteEventsCompleted $response */

        switch ($response->getResult()) {
            // @todo not handled: PrepareTimeout, CommitTimeout, ForwardTimeout
            case OperationResult::Success:
                return;
            case OperationResult::WrongExpectedVersion:
                throw WrongExpectedVersion::withExpectedVersion($this->stream, $this->expectedVersion);
            case OperationResult::StreamDeleted:
                throw StreamDeleted::with($this->stream);
            case OperationResult::InvalidTransaction:
                throw new InvalidTransaction();
            case OperationResult::AccessDenied:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }

    protected function transformResponse(Message $response): object
    {
        /** @var WriteEventsCompleted $response */
        return new WriteResult(
            $response->getLastEventNumber(),
            new Position(
                $response->getCommitPosition() ?? -1,
                $response->getPreparePosition() ?? -1
            )
        );
    }
}
