<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class SystemSettings
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
}
