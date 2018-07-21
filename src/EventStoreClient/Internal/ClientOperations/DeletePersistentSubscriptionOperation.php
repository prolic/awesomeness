<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Messages\DeletePersistentSubscription;
use Prooph\EventStore\Messages\DeletePersistentSubscriptionCompleted;
use Prooph\EventStore\Messages\DeletePersistentSubscriptionCompleted_DeletePersistentSubscriptionResult;
use Prooph\EventStoreClient\Data\PersistentSubscriptionDeleteResult;
use Prooph\EventStoreClient\Data\PersistentSubscriptionDeleteStatus;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;

/** @internal */
class DeletePersistentSubscriptionOperation extends AbstractOperation
{
    /** @var string */
    private $stream;
    /** @var string */
    private $groupName;

    public function __construct(
        Deferred $deferred,
        string $stream,
        string $groupName,
        ?UserCredentials $userCredentials
    ) {
        $this->stream = $stream;
        $this->groupName = $groupName;

        parent::__construct(
            $deferred,
            $userCredentials,
            TcpCommand::deletePersistentSubscription(),
            TcpCommand::deletePersistentSubscriptionCompleted(),
            DeletePersistentSubscriptionCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new DeletePersistentSubscription();
        $message->setEventStreamId($this->stream);
        $message->setSubscriptionGroupName($this->groupName);

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var DeletePersistentSubscriptionCompleted $response */
        switch ($response->getResult()) {
            case DeletePersistentSubscriptionCompleted_DeletePersistentSubscriptionResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case DeletePersistentSubscriptionCompleted_DeletePersistentSubscriptionResult::Fail:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' failed \'%s\'',
                    $this->groupName,
                    $this->stream,
                    $response->getReason()
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'Fail');
            case DeletePersistentSubscriptionCompleted_DeletePersistentSubscriptionResult::AccessDenied:
                $this->fail(AccessDenied::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            case DeletePersistentSubscriptionCompleted_DeletePersistentSubscriptionResult::DoesNotExist:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' does not exist',
                    $this->groupName,
                    $this->stream
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'DoesNotExist');
            default:
                throw new UnexpectedOperationResult();
        }
    }

    protected function transformResponse(Message $response)
    {
        /** @var DeletePersistentSubscriptionCompleted $response */
        if (0 === $response->getResult()) {
            $status = PersistentSubscriptionDeleteStatus::success();
        } else {
            $status = PersistentSubscriptionDeleteStatus::failure();
        }

        return new PersistentSubscriptionDeleteResult($status);
    }
}
