<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

class UnexpectedCommandException extends \RuntimeException implements Exception
{
    public function __construct(string $message = 'Unexpected command')
    {
        parent::__construct($message);
    }
}
