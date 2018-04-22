<?php

declare(strict_types=1);

namespace Prooph\EventStore\UserManagement;

use Prooph\EventStore\Exception\RuntimeException;

/** @internal */
class UserNotFound extends RuntimeException
{
    public static function withLogin(string $login): UserNotFound
    {
        return new self('User \'' . $login . '\' not found');
    }
}
