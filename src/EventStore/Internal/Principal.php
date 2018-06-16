<?php

declare(strict_types=1);

namespace Prooph\EventStore\Internal;

final class Principal
{
    /** @var string */
    private $identity;
    /** @var string[] */
    private $roles;

    public function __construct(string $identity, array $roles)
    {
        $this->identity = $identity;
        $this->roles = $roles;
    }

    public function identity(): string
    {
        return $this->identity;
    }

    /** @return string[] */
    public function roles(): array
    {
        return $this->roles;
    }

    public function toArray(): array
    {
        return [
            'identity' => $this->identity,
            'roles' => $this->roles,
        ];
    }
}