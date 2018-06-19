<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager\Messages;

class DeleteMessage implements Message
{
    /** @var string */
    private $name;
    /** @var bool */
    private $deleteStateStream;
    /** @var bool */
    private $deleteCheckpointStream;
    /** @var bool */
    private $deleteEmittedStreams;

    public function __construct(
        string $name,
        bool $deleteStateStream,
        bool $deleteCheckpointStream,
        bool $deleteEmittedStreams
    ) {
        $this->name = $name;
        $this->deleteStateStream = $deleteStateStream;
        $this->deleteCheckpointStream = $deleteCheckpointStream;
        $this->deleteEmittedStreams = $deleteEmittedStreams;
    }

    public function name(): string
    {
        return $this->name;
    }

    public function deleteStateStream(): bool
    {
        return $this->deleteStateStream;
    }

    public function deleteCheckpointStream(): bool
    {
        return $this->deleteCheckpointStream;
    }

    public function deleteEmittedStreams(): bool
    {
        return $this->deleteEmittedStreams;
    }

    public function messageName(): string
    {
        return 'DeleteMessage';
    }
}
