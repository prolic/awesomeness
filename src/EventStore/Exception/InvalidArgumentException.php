<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

class InvalidArgumentException extends \InvalidArgumentException implements EventStoreException
{
}
