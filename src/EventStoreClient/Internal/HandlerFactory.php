<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal;

use Prooph\EventStoreClient\Exception\EventStoreHandlerException;
use Prooph\EventStoreClient\Internal\Message\MessageType;

class HandlerFactory
{
    /** @throws EventStoreHandlerException */
    public function create(MessageType $messageType): Handler
    {
        $handler = null;

        switch ($messageType->type()) {

            case MessageType::Pong:
                $handler = new Handler\PongHandler();
                break;
            case MessageType::HeartBeatRequest:
                $handler = new Handler\HeartBeatRequestHandler();
                break;
            case MessageType::ReadEventCompleted:
                $handler = new Handler\ReadEventCompletedHandler();
                break;
            case MessageType::ReadAllEventsBackwardCompleted:
            case MessageType::ReadAllEventsForwardCompleted:
                $handler = new Handler\ReadAllEventsCompletedHandler();
                break;
            case MessageType::ReadStreamEventsBackwardCompleted:
                $handler = new Handler\ReadStreamEventsCompletedHandler();
                break;
            case MessageType::ReadStreamEventsForwardCompleted:
                $handler = new Handler\ReadStreamEventsCompletedHandler();
                break;
            case MessageType::SubscriptionConfirmation:
                $handler = new Handler\SubscriptionConfirmationHandler();
                break;
            case MessageType::SubscriptionDropped:
                $handler = new Handler\SubscriptionDroppedHandler();
                break;
            case MessageType::NotHandled:
                $handler = new Handler\NotHandledHandler();
                break;
            case MessageType::PersistentSubscriptionConfirmation:
                $handler = new Handler\PersistentSubscriptionConfirmationHandler();
                break;
            case MessageType::PersistentSubscriptionStreamEventAppeared:
                $handler = new Handler\PersistentSubscriptionStreamEventAppearedHandler();
                break;
            case MessageType::BadRequest:
                $handler = new Handler\BadRequestHandler();
                break;
            case MessageType::WriteEventsCompleted:
                $handler = new Handler\WriteEventsCompletedHandler();
                break;
            case MessageType::StreamEventAppeared:
                $handler = new Handler\StreamEventAppearedHandler();
                break;
            case MessageType::NotAuthenticated:
                $handler = new Handler\NotAuthenticatedHandler();
                break;
            case MessageType::TransactionStartCompleted:
                $handler = new Handler\TransactionStartCompletedHandler();
                break;
            case MessageType::TransactionWriteCompleted:
                $handler = new Handler\TransactionWriteCompletedHandler();
                break;
            case MessageType::TransactionCommitCompleted:
                $handler = new Handler\TransactionCommitCompletedHandler();
                break;
            default:
                throw new EventStoreHandlerException('Unsupported message type ' . $messageType->type());
        }

        return $handler;
    }
}
