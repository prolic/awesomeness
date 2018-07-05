<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Exception;

use Throwable;

class NotMasterException extends \RuntimeException implements Exception
{
    /** @var string */
    private $masterIp;
    /** @var int */
    private $masterPort;

    public function __construct(string $ip, int $port, Throwable $previous = null)
    {
        $this->masterIp = $ip;
        $this->masterPort = $port;

        parent::__construct("Not on master, you should connect to {$ip}:{$port} not here", 2, $previous);
    }

    public function masterIp()
    {
        return $this->masterIp;
    }

    public function masterPort()
    {
        return $this->masterPort;
    }
}
