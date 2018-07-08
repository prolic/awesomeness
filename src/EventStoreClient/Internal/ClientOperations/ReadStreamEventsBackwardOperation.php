<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Promise;
use Generator;
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
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use function Amp\call;

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
            TcpCommand::readStreamEventsBackward(),
            TcpCommand::readStreamEventsBackwardCompleted()
        );
    }

    /**
     * @return Promise<StreamEventsSlice>
     */
    public function __invoke(): Promise
    {
        return call(function (): Generator {
            /* @var TcpPackage $package */
            $package = yield $this->request();
            $message = $package->data();
            /* @var ReadStreamEventsCompleted $message */

            $records = $message->getEvents();

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
                SliceReadStatus::byValue($message->getResult()),
                $this->stream,
                $this->start,
                ReadDirection::backward(),
                $resolvedEvents,
                $message->getNextEventNumber(),
                $message->getLastEventNumber(),
                $message->getIsEndOfStream()
            );
        });
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

    protected function inspectPackage(TcpPackage $package): TcpPackage
    {
        $package = parent::inspectPackage($package);

        /** @var ReadStreamEventsCompleted $message */
        $message = $package->data();

        switch ($message->getResult()) {
            case ReadStreamEventsCompleted_ReadStreamResult::Success:
            case ReadStreamEventsCompleted_ReadStreamResult::NoStream:
            case ReadStreamEventsCompleted_ReadStreamResult::StreamDeleted:
                return $package;
            case ReadStreamEventsCompleted_ReadStreamResult::Error:
                throw new ServerError($message->getError());
            case ReadStreamEventsCompleted_ReadStreamResult::AccessDenied:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new ServerError('Unexpected ReadStreamResult');
        }
    }
}
