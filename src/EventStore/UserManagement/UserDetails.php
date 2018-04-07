<?php

declare(strict_types=1);

namespace Prooph\EventStore\UserManagement;

final class UserDetails
{
    /** @var string */
    private $login;
    /** @var string */
    private $fullName;
    /** @var string */
    private $password;
    /** @var string[] */
    private $groups;
    /** @var array */
    private $links;

    /**
     * @param string $login
     * @param string $fullName
     * @param string $password
     * @param string[] $groups
     * @param array $links
     */
    public function __construct(string $login, string $fullName, string $password, array $groups, array $links)
    {
        $this->login = $login;
        $this->fullName = $fullName;
        $this->password = $password;
        $this->groups = $groups;
        $this->links = $links;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    public function password(): string
    {
        return $this->password;
    }

    /**
     * @return string[]
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function links(): array
    {
        return $this->links;
    }
}
