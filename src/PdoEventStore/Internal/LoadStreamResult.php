<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\Internal;

/** @internal  */
class LoadStreamResult
{
    /** @var bool */
    private $found;

    public function __construct(bool $found)
    {
        $this->found = $found;
    }

    public function found(): bool
    {
        return $this->found;
    }
}
