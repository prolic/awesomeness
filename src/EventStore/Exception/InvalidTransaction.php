<?php

declare(strict_types=1);

namespace Prooph\EventStore\Exception;

/**
 * Exception thrown if there is an attempt to operate inside a
 * transaction which does not exist.
 */
class InvalidTransaction extends RuntimeException
{
}
