<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Promise;
use Generator;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\EventReadResult;
use Prooph\EventStore\Data\EventReadStatus;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\Messages\ReadEvent;
use Prooph\EventStore\Internal\Messages\ReadEventCompleted;
use Prooph\EventStore\Internal\Messages\ReadEventCompleted_ReadEventResult;
use Prooph\EventStore\Internal\Messages\ResolvedIndexedEvent;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\ReadBuffer;
use function Amp\call;

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

    /**
     * @return Promise<EventReadResult>
     */
    public function __invoke(): Promise
    {
        return call(function (): Generator {
            /* @var TcpPackage $package */
            $package = yield $this->request();
            $message = $package->data();
            /* @var ReadEventCompleted $message */

            $eventMessage = $message->getEvent();
            $event = null;
            $link = null;

            if ($eventMessage->getEvent()) {
                $event = EventMessageConverter::convertEventRecordMessageToEventRecord($eventMessage->getEvent());
            }

            if ($link = $eventMessage->getLink()) {
                $link = EventMessageConverter::convertEventRecordMessageToEventRecord($link);
            }

            $resolvedEvent = new ResolvedIndexedEvent($event, $link);

            return new EventReadResult(
                EventReadStatus::byValue($message->getResult()),
                $this->stream,
                $this->eventNumber,
                $resolvedEvent
            );
        });
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

    protected function inspectPackage(TcpPackage $package): TcpPackage
    {
        $package = parent::inspectPackage($package);

        /** @var ReadEventCompleted $message */
        $message = $package->data();

        switch ($message->getResult()) {
            case ReadEventCompleted_ReadEventResult::Success:
            case ReadEventCompleted_ReadEventResult::NotFound:
            case ReadEventCompleted_ReadEventResult::NoStream:
            case ReadEventCompleted_ReadEventResult::StreamDeleted:
                return $package;
            case ReadEventCompleted_ReadEventResult::Error:
                throw new ServerError($message->getError());
            case ReadEventCompleted_ReadEventResult::AccessDenied:
                throw AccessDenied::toStream($this->stream);
            default:
                throw new ServerError('Unexpected ReadEventResult');
        }
    }
}
