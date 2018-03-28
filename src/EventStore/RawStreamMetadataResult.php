<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class RawStreamMetadataResult
{
    private $stream;
    private $isStreamDeleted;
    private $metastreamVersion;
    private $streamMetadata;

    public function __construct(string $stream, bool $isStreamDeleted, int $metastreamVersion, string $streamMetadata)
    {
        if (empty($stream)) {
            throw new \InvalidArgumentException('Stream cannot be empty');
        }

        $this->stream = $stream;
        $this->isStreamDeleted = $isStreamDeleted;
        $this->metastreamVersion = $metastreamVersion;
        $this->streamMetadata = $streamMetadata;
    }

    public function stream(): string
    {
        return $this->stream;
    }

    public function isStreamDeleted(): bool
    {
        return $this->isStreamDeleted;
    }

    public function metastreamVersion(): int
    {
        return $this->metastreamVersion;
    }

    public function streamMetadata(): string
    {
        return $this->streamMetadata;
    }
}
