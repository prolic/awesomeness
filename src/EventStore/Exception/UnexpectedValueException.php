<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class UnexpectedValueException extends \UnexpectedValueException implements EventStoreException
{
}
