<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Amp\Deferred;
use Prooph\EventStore\Data\EventId;
use Prooph\EventStore\Data\PersistentSubscriptionNakEventAction;
use Prooph\EventStore\Data\PersistentSubscriptionResolvedEvent;
use Prooph\EventStore\Data\SubscriptionDropReason;
use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\ConnectToPersistentSubscription;
use Prooph\EventStore\Messages\PersistentSubscriptionAckEvents;
use Prooph\EventStore\Messages\PersistentSubscriptionConfirmation;
use Prooph\EventStore\Messages\PersistentSubscriptionNakEvents;
use Prooph\EventStore\Messages\PersistentSubscriptionStreamEventAppeared;
use Prooph\EventStore\Messages\SubscriptionDropped;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Exception\MaximumSubscribersReachedException;
use Prooph\EventStoreClient\Exception\PersistentSubscriptionDeletedException;
use Prooph\EventStoreClient\Internal\ConnectToPersistentSubscriptions;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\EventStoreSubscription;
use Prooph\EventStoreClient\Internal\PersistentEventStoreSubscription;
use Rxnet\EventStore\Data\SubscriptionDropped_SubscriptionDropReason;

/** @internal */
class ConnectToPersistentSubscriptionOperation extends AbstractSubscriptionOperation implements ConnectToPersistentSubscriptions
{
    /** @var string */
    private $groupName;
    /** @var int */
    private $bufferSize;
    /** @var string */
    private $subscriptionId;

    public function __construct(
        Deferred $deferred,
        string $groupName,
        int $bufferSize,
        string $streamId,
        ?UserCredentials $userCredentials,
        callable $eventAppeared,
        ?callable $subscriptionDropped,
        callable $getConnection
    ) {
        parent::__construct(
            $deferred,
            $streamId,
            false,
            $userCredentials,
            $eventAppeared,
            $subscriptionDropped,
            $getConnection
        );

        $this->groupName = $groupName;
        $this->bufferSize = $bufferSize;
    }

    protected function createSubscriptionPackage(): TcpPackage
    {
        $message = new ConnectToPersistentSubscription();
        $message->setEventStreamId($this->streamId);
        $message->setSubscriptionId($this->groupName);
        $message->setAllowedInFlightMessages($this->bufferSize);

        return new TcpPackage(
            TcpCommand::connectToPersistentSubscription(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $this->userCredentials
        );
    }

    protected function preInspectPackage(TcpPackage $package): ?InspectionResult
    {
        if ($package->command()->equals(TcpCommand::persistentSubscriptionConfirmation())) {
            $message = new PersistentSubscriptionConfirmation();
            $message->mergeFromString($package->data());

            $this->confirmSubscription($message->getLastCommitPosition(), $message->getLastEventNumber());
            $this->subscriptionId = $message->getSubscriptionId();

            return new InspectionResult(InspectionDecision::subscribed(), 'SubscriptionConfirmation');
        }

        if ($package->command()->equals(TcpCommand::persistentSubscriptionStreamEventAppeared())) {
            $message = new PersistentSubscriptionStreamEventAppeared();
            $message->mergeFromString($package->data());

            $event = EventMessageConverter::convertResolvedIndexedEventMessageToResolvedEvent($message->getEvent());
            $this->eventAppeared(new PersistentSubscriptionResolvedEvent($event, $dto->getRetryCount()));

            return new InspectionResult(InspectionDecision::doNothing(), 'StreamEventAppeared');
        }

        if ($package->command()->equals(TcpCommand::subscriptionDropped())) {
            $message = new SubscriptionDropped();
            $message->mergeFromString($package->data());

            if ($message->getReason() === SubscriptionDropped_SubscriptionDropReason::AccessDenied) {
                $this->dropSubscription(SubscriptionDropReason::accessDenied(), new AccessDenied('You do not have access to the stream'));

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropped_SubscriptionDropReason::NotFound) {
                $this->dropSubscription(SubscriptionDropReason::notFound(), new InvalidArgumentException('Subscription not found'));

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropped_SubscriptionDropReason::PersistentSubscriptionDeleted) {
                $this->dropSubscription(SubscriptionDropReason::persistentSubscriptionDeleted(), new PersistentSubscriptionDeletedException());

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            if ($message->getReason() === SubscriptionDropped_SubscriptionDropReason::SubscriberMaxCountReached) {
                $this->dropSubscription(SubscriptionDropReason::maxSubscribersReached(), new MaximumSubscribersReachedException());

                return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
            }

            $this->dropSubscription(SubscriptionDropReason::byValue($message->getReason()), null, ($this->getConnection)());

            return new InspectionResult(InspectionDecision::endOperation(), 'SubscriptionDropped');
        }

        return null;
    }

    protected function createSubscriptionObject(int $lastCommitPosition, ?int $lastEventNumber): EventStoreSubscription
    {
        return new PersistentEventStoreSubscription(
            $this,
            $this->streamId,
            $lastCommitPosition,
            $lastEventNumber
        );
    }

    /** @param EventId[] $eventIds */
    public function notifyEventsProcessed(array $eventIds): void
    {
        if (empty($eventIds)) {
            throw new InvalidArgumentException('EventIds cannot be empty');
        }

        $ids = \array_map(
            function (EventId $eventId): string {
                return $eventId->toBinary();
            },
            $eventIds
        );

        $message = new PersistentSubscriptionAckEvents();
        $message->setSubscriptionId($this->subscriptionId);
        $message->setProcessedEventIds($ids);

        $package = new TcpPackage(
            TcpCommand::persistentSubscriptionAckEvents(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $this->userCredentials
        );

        $this->enqueueSend($package);
    }

    public function notifyEventsFailed(
        array $eventIds,
        PersistentSubscriptionNakEventAction $action,
        string $reason
    ): void {
        if (empty($eventIds)) {
            throw new InvalidArgumentException('EventIds cannot be empty');
        }

        $ids = \array_map(
            function (EventId $eventId): string {
                $eventId->toBinary();
            },
            $eventIds
        );

        $message = new PersistentSubscriptionNakEvents();
        $message->setSubscriptionId($this->subscriptionId);
        $message->setProcessedEventIds($ids);
        $message->setMessage($reason);
        $message->setAction($action->value());

        $package = new TcpPackage(
            TcpCommand::persistentSubscriptionNakEvents(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $this->userCredentials
        );

        $this->enqueueSend($package);
    }
}
