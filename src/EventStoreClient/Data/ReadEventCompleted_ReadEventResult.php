<?php

declare(strict_types=1);
// Generated by the protocol buffer compiler.  DO NOT EDIT!
// source: ClientMessageDtos.proto

namespace Prooph\EventStoreClient\Data;

/**
 * Protobuf enum <code>Prooph\EventStoreClient\Data\ReadEventCompleted\ReadEventResult</code>
 */
class ReadEventCompleted_ReadEventResult
{
    /**
     * Generated from protobuf enum <code>Success = 0;</code>
     */
    const Success = 0;
    /**
     * Generated from protobuf enum <code>NotFound = 1;</code>
     */
    const NotFound = 1;
    /**
     * Generated from protobuf enum <code>NoStream = 2;</code>
     */
    const NoStream = 2;
    /**
     * Generated from protobuf enum <code>StreamDeleted = 3;</code>
     */
    const StreamDeleted = 3;
    /**
     * Generated from protobuf enum <code>Error = 4;</code>
     */
    const Error = 4;
    /**
     * Generated from protobuf enum <code>AccessDenied = 5;</code>
     */
    const AccessDenied = 5;
}
