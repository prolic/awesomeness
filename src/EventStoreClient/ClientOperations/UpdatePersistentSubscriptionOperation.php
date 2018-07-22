<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\ClientOperations;

use Amp\Deferred;
use Google\Protobuf\Internal\Message;
use Prooph\EventStoreClient\Common\SystemConsumerStrategies;
use Prooph\EventStoreClient\Data\PersistentSubscriptionSettings;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionUpdateResult;
use Prooph\EventStoreClient\Internal\PersistentSubscriptionUpdateStatus;
use Prooph\EventStoreClient\Data\UserCredentials;
use Prooph\EventStoreClient\Exception\AccessDeniedException;
use Prooph\EventStoreClient\Exception\InvalidOperationException;
use Prooph\EventStoreClient\Exception\UnexpectedOperationResult;
use Prooph\EventStoreClient\Internal\SystemData\InspectionDecision;
use Prooph\EventStoreClient\Internal\SystemData\InspectionResult;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscription;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted;
use Prooph\EventStoreClient\Messages\ClientMessages\UpdatePersistentSubscriptionCompleted\UpdatePersistentSubscriptionResult;
use Prooph\EventStoreClient\Transport\Tcp\TcpCommand;
use Psr\Log\LoggerInterface as Logger;

/** @internal */
class UpdatePersistentSubscriptionOperation extends AbstractOperation
{
    /** @var string */
    private $stream;
    /** @var int */
    private $groupName;
    /** @var PersistentSubscriptionSettings */
    private $settings;

    public function __construct(
        Logger $logger,
        Deferred $deferred,
        string $stream,
        string $groupNameName,
        PersistentSubscriptionSettings $settings,
        ?UserCredentials $userCredentials
    ) {
        $this->stream = $stream;
        $this->groupName = $groupNameName;
        $this->settings = $settings;

        parent::__construct(
            $logger,
            $deferred,
            $userCredentials,
            TcpCommand::updatePersistentSubscription(),
            TcpCommand::updatePersistentSubscriptionCompleted(),
            UpdatePersistentSubscriptionCompleted::class
        );
    }

    protected function createRequestDto(): Message
    {
        $message = new UpdatePersistentSubscription();
        $message->setSubscriptionGroupName($this->groupName);
        $message->setEventStreamId($this->stream);
        $message->setResolveLinkTos($this->settings->resolveLinkTos());
        $message->setStartFrom($this->settings->startFrom());
        $message->setMessageTimeoutMilliseconds($this->settings->messageTimeoutMilliseconds());
        $message->setRecordStatistics($this->settings->extraStatistics());
        $message->setLiveBufferSize($this->settings->liveBufferSize());
        $message->setReadBatchSize($this->settings->readBatchSize());
        $message->setBufferSize($this->settings->bufferSize());
        $message->setMaxRetryCount($this->settings->maxRetryCount());
        $message->setPreferRoundRobin($this->settings->namedConsumerStrategy()->name() === SystemConsumerStrategies::RoundRobin);
        $message->setCheckpointAfterTime($this->settings->checkPointAfterMilliseconds());
        $message->setCheckpointMaxCount($this->settings->maxCheckPointCount());
        $message->setCheckpointMinCount($this->settings->minCheckPointCount());
        $message->setSubscriberMaxCount($this->settings->maxSubscriberCount());
        $message->setNamedConsumerStrategy($this->settings->namedConsumerStrategy()->name());

        return $message;
    }

    protected function inspectResponse(Message $response): InspectionResult
    {
        /** @var UpdatePersistentSubscriptionCompleted $response */
        switch ($response->getResult()) {
            case UpdatePersistentSubscriptionResult::Success:
                $this->succeed($response);

                return new InspectionResult(InspectionDecision::endOperation(), 'Success');
            case UpdatePersistentSubscriptionResult::Fail:
                $this->fail(new InvalidOperationException(\sprintf(
                    'Subscription group \'%s\' on stream \'%s\' failed \'%s\'',
                    $this->groupName,
                    $this->stream,
                    $response->getReason()
                )));

                return new InspectionResult(InspectionDecision::endOperation(), 'Fail');
            case UpdatePersistentSubscriptionResult::AccessDenied:
                $this->fail(AccessDeniedException::toStream($this->stream));

                return new InspectionResult(InspectionDecision::endOperation(), 'AccessDenied');
            case UpdatePersistentSubscriptionResult::DoesNotExist:
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
        /** @var UpdatePersistentSubscriptionCompleted $response */
        return new PersistentSubscriptionUpdateResult(
            PersistentSubscriptionUpdateStatus::success()
        );
    }

    public function name(): string
    {
        return 'UpdatePersistentSubscription';
    }
}