<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Data\DeleteResult;
use Prooph\EventStore\Data\Position;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Exception\InvalidTransaction;
use Prooph\EventStore\Exception\StreamDeleted;
use Prooph\EventStore\Exception\WrongExpectedVersion;
use Prooph\EventStore\Messages\DeleteStream;
use Prooph\EventStore\Messages\DeleteStreamCompleted;
use Prooph\EventStore\Messages\OperationResult;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpDispatcher;
use Prooph\EventStoreClient\Exception\ServerError;
use Prooph\EventStoreClient\Internal\ReadBuffer;

/** @internal */
class DeleteStreamOperation extends AbstractOperation
{
    /** @var bool */
    private $requireMaster;
    /** @var string */
    private $stream;
    /** @var int */
    private $expectedVersion;
    /** @var bool */
    private $hardDelete;

    public function __construct(
        TcpDispatcher $dispatcher,
        ReadBuffer $readBuffer,
        bool $requireMaster,
        string $stream,
        int $expectedVersion,
        bool $hardDelete,
        ?UserCredentials $userCredentials
    ) {
        $this->requireMaster = $requireMaster;
        $this->stream = $stream;
        $this->expectedVersion = $expectedVersion;
        $this->hardDelete = $hardDelete;

        parent::__construct(
            $dispatcher,
            $readBuffer,
            $userCredentials,
            TcpCommand::deleteStream(),
            TcpCommand::deleteStreamCompleted()
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new DeleteStream();
        $message->setEventStreamId($this->stream);
        $message->setExpectedVersion($this->expectedVersion);
        $message->setHardDelete($this->hardDelete);
        $message->setRequireMaster($this->requireMaster);

        return $message;
    }

    protected function inspectResponse(Message $response): void
    {
        /** @var DeleteStreamCompleted $response */

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
        /** @var DeleteStreamCompleted $response */
        return new DeleteResult(new Position(
            $response->getCommitPosition() ?? -1,
            $response->getCommitPosition() ?? -1)
        );
    }
}
