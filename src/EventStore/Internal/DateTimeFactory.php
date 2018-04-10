<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

use DateTimeImmutable;
use DateTimeZone;

abstract class DateTimeFactory
{
    public static function create(string $dateTimeString): DateTimeImmutable
    {
        return DateTimeImmutable::createFromFormat(
            'Y-m-d\TH:i:s.u\Z',
            $dateTimeString,
            new DateTimeZone('UTC')
        );
    }
}
