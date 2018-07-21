<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\ClientOperations;

use Prooph\EventStore\Internal\SystemData\InspectionDecision;
use Prooph\EventStore\Internal\SystemData\InspectionResult;
use Prooph\EventStore\Messages\StreamEventAppeared;
use Prooph\EventStore\Messages\SubscribeToStream;
use Prooph\EventStore\Messages\SubscriptionConfirmation;
use Prooph\EventStore\Transport\Tcp\TcpCommand;
use Prooph\EventStore\Transport\Tcp\TcpFlags;
use Prooph\EventStore\Transport\Tcp\TcpPackage;
use Prooph\EventStoreClient\Internal\EventMessageConverter;
use Prooph\EventStoreClient\Internal\EventStoreSubscription;
use Prooph\EventStoreClient\Internal\VolatileEventStoreSubscription;

/** @internal  */
class VolatileSubscriptionOperation extends AbstractSubscriptionOperation
{
    protected function createSubscriptionPackage(): TcpPackage
    {
        $message = new SubscribeToStream();
        $message->setEventStreamId($this->streamId);
        $message->setResolveLinkTos($this->resolveLinkTos);

        return new TcpPackage(
            TcpCommand::subscribeToStream(),
            $this->userCredentials ? TcpFlags::authenticated() : TcpFlags::none(),
            $this->correlationId,
            $message->serializeToString(),
            $this->userCredentials
        );
    }

    protected function preInspectPackage(TcpPackage $package): ?InspectionResult
    {
        if ($package->command()->equals(TcpCommand::subscriptionConfirmation())) {
            $message = new SubscriptionConfirmation();
            $message->mergeFromString($package->data());

            $this->confirmSubscription($message->getLastCommitPosition(), $message->getLastEventNumber());

            return new InspectionResult(InspectionDecision::subscribed(), 'SubscriptionConfirmation');
        }

        if ($package->command()->equals(TcpCommand::streamEventAppeared())) {
            $message = new StreamEventAppeared();
            $message->mergeFromString($package->data());
            $event = EventMessageConverter::convertResolvedEventMessageToResolvedEvent($message->getEvent());
            $this->eventAppeared($event);

            return new InspectionResult(InspectionDecision::doNothing(), 'StreamEventAppeared');
        }

        return null;
    }

    protected function createSubscriptionObject(int $lastCommitPosition, ?int $lastEventNumber): EventStoreSubscription
    {
        return new VolatileEventStoreSubscription(
                $this,
                $this->streamId,
                $lastCommitPosition,
                $lastEventNumber
        );
    }
}
