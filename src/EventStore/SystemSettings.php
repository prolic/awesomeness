<?php

declare(strict_types=1);

namespace Prooph\EventStore;

use Prooph\EventStore\Common\SystemRoles;

class SystemSettings
{
    /**
     * Default access control list for new user streams.
     * @var StreamAcl
     */
    private $userStreamAcl;

    /**
     * Default access control list for new system streams.
     * @var StreamAcl
     */
    private $systemStreamAcl;

    public static function default(): SystemSettings
    {
        return new self(
            new StreamAcl(
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All],
                [SystemRoles::All]
            ),
            new StreamAcl(
                [SystemRoles::Admins],
                [SystemRoles::Admins],
                [SystemRoles::Admins],
                [SystemRoles::Admins],
                [SystemRoles::Admins]
            )
        );
    }

    public function __construct(StreamAcl $userStreamAcl, StreamAcl $systemStreamAcl)
    {
        $this->userStreamAcl = $userStreamAcl;
        $this->systemStreamAcl = $systemStreamAcl;
    }

    public function userStreamAcl(): StreamAcl
    {
        return $this->userStreamAcl;
    }

    public function systemStreamAcl(): StreamAcl
    {
        return $this->systemStreamAcl;
    }

    public function toArray(): array
    {
        return [
            '$userStreamAcl' => $this->userStreamAcl->toArray(),
            '$systemStreamAcl' => $this->systemStreamAcl->toArray(),
        ];
    }

    public static function fromArray(array $data): SystemSettings
    {
        if (! isset($data['$userStreamAcl'])) {
            throw new \InvalidArgumentException('$userStreamAcl is missing');
        }

        if (! isset($data['$systemStreamAcl'])) {
            throw new \InvalidArgumentException('$systemStreamAcl is missing');
        }

        return new self(StreamAcl::fromArray($data['$userStreamAcl']), StreamAcl::fromArray($data['$systemStreamAcl']));
    }
}
