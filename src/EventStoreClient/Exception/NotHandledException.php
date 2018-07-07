<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class NotHandledException extends \RuntimeException implements Exception
{
    public static function notReady(): NotHandledException
    {
        return new self('Not handled: not ready');
    }

    public static function tooBusy(): NotHandledException
    {
        return new self('Not handled: too busy');
    }

    public static function notMaster(): NotHandledException
    {
        return new self('Not handled: not master, don\'t connect here');
    }
}
