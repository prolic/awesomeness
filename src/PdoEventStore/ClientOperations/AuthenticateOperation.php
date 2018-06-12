<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\ClientOperations;

use PDO;
use Prooph\EventStore\Common\SystemRoles;
use Prooph\EventStore\Exception\AccessDenied;
use Prooph\EventStore\UserCredentials;

/** @internal */
class AuthenticateOperation
{
    public function __invoke(PDO $connection, ?UserCredentials $userCredentials): array
    {
        if (null === $userCredentials) {
            return [SystemRoles::All];
        }

        $statement = $connection->prepare(<<<SQL
SELECT password_hash, CONCAT(users.username, ',', STRING_AGG(user_roles.rolename, ',')) as user_roles
    FROM users
    LEFT JOIN user_roles ON users.username = user_roles.username
    WHERE users.username = ?
    GROUP BY users.username
SQL
        );
        $statement->execute([$userCredentials->username()]);

        if (0 === $statement->rowCount()) {
            throw AccessDenied::login($userCredentials->username());
        }

        $statement->setFetchMode(PDO::FETCH_OBJ);
        $data = $statement->fetch();

        if ($data->disabled
            || ! \password_verify($userCredentials->password(), $data->password_hash)
        ) {
            throw AccessDenied::login($userCredentials->username());
        }

        return \array_merge(\explode(',', $data->user_roles), [SystemRoles::All]);
    }
}
