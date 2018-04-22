<?php

declare(strict_types=1);

namespace Prooph\EventStore\UserManagement;

final class UserDetails
{
    /** @var string */
    private $login;
    /** @var string */
    private $fullName;
    /** @var string[] */
    private $groups;
    /** @var bool */
    private $disabled;

    /** @internal */
    public function __construct(string $login, string $fullName, array $groups, bool $disabled)
    {
        $this->login = $login;
        $this->fullName = $fullName;
        $this->groups = $groups;
        $this->disabled = $disabled;
    }

    public function login(): string
    {
        return $this->login;
    }

    public function fullName(): string
    {
        return $this->fullName;
    }

    /**
     * @return string[]
     */
    public function groups(): array
    {
        return $this->groups;
    }

    public function disabled(): bool
    {
        return $this->disabled;
    }
}
