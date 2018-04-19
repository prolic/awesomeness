<?php

declare(strict_types=1);

namespace Prooph\PdoEventStore\Internal;

/** @internal  */
class LoadStreamIdResult
{
    /** @var bool */
    private $found;
    /** @var string|null */
    private $streamId;

    public function __construct(bool $found, ?string $streamId)
    {
        $this->found = $found;
        $this->streamId = $streamId;
    }

    public function streamId(): ?string
    {
        return $this->streamId;
    }

    public function found(): bool
    {
        return $this->found;
    }
}
