<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Internal\Message;

class Credentials
{
    /** @var string */
    private $username;
    /** @var string */
    private $password;

    public function __construct(string $username, string $password)
    {
        $this->username = $username;
        $this->password = $password;
    }

    public function username(): string
    {
        return $this->username;
    }

    public function password(): string
    {
        return $this->password;
    }
}
