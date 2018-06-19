<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class Response
{
    /** @var mixed */
    private $result;
    /** @var bool */
    private $error = false;

    public function __construct($result, bool $error)
    {
        $this->result = $result;
        $this->error = $error;
    }

    public function result()
    {
        return $this->result;
    }

    public function error(): bool
    {
        return $this->error;
    }
}
