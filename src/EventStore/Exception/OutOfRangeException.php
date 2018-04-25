<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class OutOfRangeException extends \OutOfRangeException implements EventStoreException
{
}
