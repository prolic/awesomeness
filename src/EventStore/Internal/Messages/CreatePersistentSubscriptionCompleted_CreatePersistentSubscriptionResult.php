<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: ClientMessageDtos.proto

namespace Prooph\EventStore\Internal\Messages;

/**
 * Protobuf enum <code>Prooph\EventStore\Internal\Messages\CreatePersistentSubscriptionCompleted\CreatePersistentSubscriptionResult</code>
 */
class CreatePersistentSubscriptionCompleted_CreatePersistentSubscriptionResult
{
    /**
     * Generated from protobuf enum <code>Success = 0;</code>
     */
    const Success = 0;
    /**
     * Generated from protobuf enum <code>AlreadyExists = 1;</code>
     */
    const AlreadyExists = 1;
    /**
     * Generated from protobuf enum <code>Fail = 2;</code>
     */
    const Fail = 2;
    /**
     * Generated from protobuf enum <code>AccessDenied = 3;</code>
     */
    const AccessDenied = 3;
}