<?php

declare(strict_types=1);

namespace Prooph\EventStore\Transport\Tcp;

use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Messages\NotHandled;
use Prooph\EventStore\Messages\NotHandled_MasterInfo;
use Prooph\EventStore\Messages\PersistentSubscriptionConfirmation;
use Prooph\EventStore\Messages\PersistentSubscriptionStreamEventAppeared;
use Prooph\EventStore\Messages\ReadAllEventsCompleted;
use Prooph\EventStore\Messages\ReadEventCompleted;
use Prooph\EventStore\Messages\ReadStreamEventsCompleted;
use Prooph\EventStore\Messages\StreamEventAppeared;
use Prooph\EventStore\Messages\SubscriptionConfirmation;
use Prooph\EventStore\Messages\SubscriptionDropped;
use Prooph\EventStore\Messages\TransactionCommitCompleted;
use Prooph\EventStore\Messages\TransactionStartCompleted;
use Prooph\EventStore\Messages\TransactionWriteCompleted;
use Prooph\EventStore\Messages\WriteEventsCompleted;

class TcpPackageFactory
{
    public function build(TcpCommand $command, TcpFlags $flags, string $correlationId, ?string $data): TcpPackage
    {
        switch ($command->value()) {
            case TcpCommand::Pong:
                return new TcpPackage($command, $flags, $correlationId);
            case TcpCommand::HeartbeatRequestCommand:
            case TcpCommand::HeartbeatResponseCommand:
                return new TcpPackage($command, $flags, $correlationId);
            case TcpCommand::ReadEventCompleted:
                $dataObject = new ReadEventCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::ReadAllEventsBackwardCompleted:
            case TcpCommand::ReadAllEventsForwardCompleted:
                $dataObject = new ReadAllEventsCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::ReadStreamEventsBackwardCompleted:
            case TcpCommand::ReadStreamEventsForwardCompleted:
                $dataObject = new ReadStreamEventsCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::SubscriptionConfirmation:
                $dataObject = new SubscriptionConfirmation();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::SubscriptionDropped:
                $dataObject = new SubscriptionDropped();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::NotHandled:
                $dataObject = new NotHandled();
                $dataObject->mergeFromString($data);

                if (2 === $dataObject->getReason()) {
                    $dataObject = new NotHandled_MasterInfo();
                    $dataObject->mergeFromString($data);
                }

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::PersistentSubscriptionConfirmation:
                $dataObject = new PersistentSubscriptionConfirmation();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::PersistentSubscriptionStreamEventAppeared:
                $dataObject = new PersistentSubscriptionStreamEventAppeared();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::BadRequest:
                throw new RuntimeException("Bad Request: $data");
            case TcpCommand::WriteEventsCompleted:
                $dataObject = new WriteEventsCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::StreamEventAppeared:
                $dataObject = new StreamEventAppeared();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::NotAuthenticatedException:
                throw new RuntimeException("Not Authenticated: $data");
            case TcpCommand::TransactionStartCompleted:
                $dataObject = new TransactionStartCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::TransactionWriteCompleted:
                $dataObject = new TransactionWriteCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            case TcpCommand::TransactionCommitCompleted:
                $dataObject = new TransactionCommitCompleted();
                $dataObject->mergeFromString($data);

                return new TcpPackage($command, $flags, $correlationId, $dataObject);
            default:
                throw new RuntimeException('Unsupported command "' . $command->value() . '"');
        }
    }
}
