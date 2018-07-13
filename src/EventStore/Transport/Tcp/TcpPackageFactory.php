<?php

declare(strict_types=1);

namespace Prooph\EventStore\Transport\Tcp;

use Prooph\EventStore\Data\UserCredentials;
use Prooph\EventStore\Exception\RuntimeException;
use Prooph\EventStore\Messages\ClientIdentified;
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
    public function build(
        TcpCommand $command,
        TcpFlags $flags,
        string $correlationId,
        ?string $data,
        ?UserCredentials $credentials
    ): TcpPackage {
        switch ($command->value()) {
            case TcpCommand::Pong:
                break;
            case TcpCommand::HeartbeatRequestCommand:
            case TcpCommand::HeartbeatResponseCommand:
                break;
            case TcpCommand::ReadEventCompleted:
                $dto = new ReadEventCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::ReadAllEventsBackwardCompleted:
            case TcpCommand::ReadAllEventsForwardCompleted:
                $dto = new ReadAllEventsCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::ReadStreamEventsBackwardCompleted:
            case TcpCommand::ReadStreamEventsForwardCompleted:
                $dto = new ReadStreamEventsCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::SubscriptionConfirmation:
                $dto = new SubscriptionConfirmation();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::SubscriptionDropped:
                $dto = new SubscriptionDropped();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::NotHandled:
                $dto = new NotHandled();
                $dto->mergeFromString($data);

                if (2 === $dto->getReason()) {
                    $dto = new NotHandled_MasterInfo();
                    $dto->mergeFromString($data);
                }

                break;
            case TcpCommand::PersistentSubscriptionConfirmation:
                $dto = new PersistentSubscriptionConfirmation();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::PersistentSubscriptionStreamEventAppeared:
                $dto = new PersistentSubscriptionStreamEventAppeared();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::BadRequest:
                throw new RuntimeException("Bad Request: $data");
            case TcpCommand::WriteEventsCompleted:
                $dto = new WriteEventsCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::StreamEventAppeared:
                $dto = new StreamEventAppeared();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::NotAuthenticatedException:
                throw new RuntimeException("Not Authenticated: $data");
            case TcpCommand::TransactionStartCompleted:
                $dto = new TransactionStartCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::TransactionWriteCompleted:
                $dto = new TransactionWriteCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::TransactionCommitCompleted:
                $dto = new TransactionCommitCompleted();
                $dto->mergeFromString($data);
                break;
            case TcpCommand::ClientIdentified:
                $dto = new ClientIdentified();
                $dto->mergeFromString($data);
                break;
            default:
                throw new RuntimeException('Unsupported command "' . $command->value() . '"');
        }

        return new TcpPackage($command, $flags, $correlationId, $dto, $credentials);
    }
}
