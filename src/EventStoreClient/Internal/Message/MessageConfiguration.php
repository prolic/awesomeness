<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

class MessageConfiguration
{
    public const Int32Length = 4;
    public const CorrelationIdLength = 16;
    public const HeaderLenth = 18;
    public const MessageTypeOffset = 4;
    public const FlagOffset = 5;
    public const CorrelationIdOffset = 6;
    public const DataOffset = 22;
    public const FlagsNone = 0x00;
    public const FlagsAuthorization = 0x01;

    private function __construct()
    {
    }
}
