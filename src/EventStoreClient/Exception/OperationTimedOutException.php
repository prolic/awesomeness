<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

use Prooph\EventStoreClient\Internal\ClientOperation;

class OperationTimedOutException extends \RuntimeException implements Exception
{
    /** @var ClientOperation */
    private $operation;

    private function __construct(string $message, ClientOperation $operation)
    {
        parent::__construct($message);
        $this->operation = $operation;
    }

    public static function with(string $connectionName, ClientOperation $operation): OperationTimedOutException
    {
        return new self(
            \sprintf(
                'EventStoreConnection \'%s\': operation never got response from server',
                $connectionName
            ),
            $operation
        );
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }
}
