<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class AccessDenied extends RuntimeException
{
    public static function login(string $username): AccessDenied
    {
        return new self(sprintf(
            'Access to event store with username \'%s\' is denied',
            $username
        ));
    }

    public static function toStream(string $stream): AccessDenied
    {
        return new self(sprintf(
            'Access to stream \'%s\' is denied',
            $stream
        ));
    }

    public static function toSubscription(string $stream, string $groupName): AccessDenied
    {
        return new self(sprintf(
            'Access to subscription with stream \'%s\' and group name \'%s\' is denied',
            $stream,
            $groupName
        ));
    }

    public static function toUserManagementOperation(): AccessDenied
    {
        return new self('Access to user management operation denied');
    }
}
