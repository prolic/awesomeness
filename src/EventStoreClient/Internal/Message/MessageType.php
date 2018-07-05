<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use ReflectionClass;

class MessageType
{
    public const HeartBeatRequest = 0x01;
    public const HeartBeatResponse = 0x02;

    public const Ping = 0x03;
    public const Pong = 0x04;

    public const PrepareAck = 0x05;
    public const CommitAck = 0x06;

    public const SlaveAssignment = 0x07;
    public const CloneAssignment = 0x08;

    public const SubscribeReplica = 0x10;
    public const ReplicaSubscriptionAck = 0x11;
    public const CreateChunk = 0x12;
    public const RawChunkBulk = 0x13;
    public const DataChunkBulk = 0x14;
    public const ReplicaSubscriptionEntry = 0x15;
    public const ReplicaSubscribed = 0x16;

    public const WriteEvents = 0x82;
    public const WriteEventsCompleted = 0x83;

    public const TransactionStart = 0x84;
    public const TransactionStartCompleted = 0x85;
    public const TransactionWrite = 0x86;
    public const TransactionWriteCompleted = 0x87;
    public const TransactionCommit = 0x88;
    public const TransactionCommitCompleted = 0x89;

    public const DeleteStream = 0x8A;
    public const DeleteStreamCompleted = 0x8B;

    public const Read = 0xB0;
    public const ReadEventCompleted = 0xB1;
    public const ReadStreamEventsForward = 0xB2;
    public const ReadStreamEventsForwardCompleted = 0xB3;
    public const ReadStreamEventsBackward = 0xB4;
    public const ReadStreamEventsBackwardCompleted = 0xB5;
    public const ReadAllEventsForward = 0xB6;
    public const ReadAllEventsForwardCompleted = 0xB7;
    public const ReadAllEventsBackward = 0xB8;
    public const ReadAllEventsBackwardCompleted = 0xB9;

    public const SubscribeToStream = 0xC0;
    public const SubscriptionConfirmation = 0xC1;
    public const StreamEventAppeared = 0xC2;
    public const UnsubscribeFromStream = 0xC3;
    public const SubscriptionDropped = 0xC4;

    public const ConnectoToPersistentSubscription = 0xC5;
    public const PersistentSubscriptionConfirmation = 0xC6;
    public const PersistentSubscriptionStreamEventAppeared = 0xC7;
    public const CreatePersistentSubscription = 0xC8;
    public const CreatePersistentSubscriptionCompleted = 0xC9;
    public const DeletePersistentSubscription = 0xCA;
    public const DeletePersistentSubscriptionCompleted = 0xCB;
    public const PersistentSubscriptionAckEvents = 0xCC;
    public const PersistentSubscriptionNackEvents = 0xCD;
    public const UpdatePersistentSubscription = 0xCE;
    public const UpdatePersistentSubscriptionCompleted = 0xCF;

    public const ScavengeDatabase = 0xD0;
    public const ScavengeDatabaseCompleted = 0xD1;

    public const BadRequest = 0xF0;
    public const NotHandled = 0xF1;
    public const Authenticate = 0xF2;
    public const Authenticated = 0xF3;
    public const NotAuthenticated = 0xF4;

    /** @var int */
    private $messageType;

    /** @throws InvalidArgumentException */
    public function __construct(int $messageType)
    {
        $found = false;

        foreach ((new ReflectionClass($this))->getConstants() as $constant) {
            if ($constant === $messageType) {
                $found = true;
                break;
            }
        }

        if (! $found) {
            throw new InvalidArgumentException($messageType . ' is not available.');
        }

        $this->messageType = $messageType;
    }

    public function type(): int
    {
        return $this->messageType;
    }
}
