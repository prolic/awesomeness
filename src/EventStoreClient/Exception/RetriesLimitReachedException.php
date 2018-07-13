<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

use Prooph\EventStoreClient\Internal\ClientOperation;

class RetriesLimitReachedException extends \RuntimeException implements Exception
{
    /** @var ClientOperation */
    private $operation;

    private function __construct(string $message, ClientOperation $operation)
    {
        parent::__construct($message);
        $this->operation = $operation;
    }

    public static function with(ClientOperation $operation, int $retries): RetriesLimitReachedException
    {
        return new self(
            \sprintf(
                'Operation reached retries limit: \'%s\'',
                $retries
            ),
            $operation
        );
    }

    public function operation(): ClientOperation
    {
        return $this->operation;
    }
}
