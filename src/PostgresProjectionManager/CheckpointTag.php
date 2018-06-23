<?php

declare(strict_types=1);

namespace Prooph\PostgresProjectionManager;

class CheckpointTag
{
    private const Stream = 0;
    private const MultiStream = 1;
    private const Position = 2;

    /** @var array */
    private $checkpoint;
    /** @var int */
    private $mode;

    private function __construct(array $initialCheckpoint, int $mode)
    {
        $this->checkpoint = $initialCheckpoint;
        $this->mode = $mode;
    }

    public static function fromJsonString(string $jsonString): CheckpointTag
    {
        $data = \json_decode($jsonString, true);

        if (\json_last_error() !== \JSON_ERROR_NONE) {
            throw new \RuntimeException('Invalid json string passed');
        }

        if (isset($data['$s']) && count($data['$s']) === 1) {
            return new self($data, self::Stream);
        }

        if (isset($data['$s']) && count($data['$s']) > 1) {
            return new self($data, self::MultiStream);
        }

        if (isset($data['C']) && isset($data['P'])) {
            return new self($data, self::Position);
        }

        throw new \RuntimeException('Invalid data passed');
    }

    public static function fromStreamPosition(string $stream, int $position): CheckpointTag
    {
        return new self(
            [
                '$s' => [
                    $stream => $position,
                ],
            ],
            self::Stream
        );
    }

    public static function fromStreamPositions(array $streamPositions): CheckpointTag
    {
        return new self(
            [
                '$s' => $streamPositions,
            ],
            self::MultiStream
        );
    }

    public static function fromPosition(int $preparePosition, int $commitPosition): CheckpointTag
    {
        return new self(
            [
                'C' => $commitPosition,
                'P' => $preparePosition,
            ],
            self::Position
        );
    }

    public function updateStreamPosition(string $stream, int $position): void
    {
        $this->checkpoint['$s'][$stream] = $position;
    }

    public function updatePosition(int $preparePosition, int $commitPosition): void
    {
        $this->checkpoint = [
            'C' => $commitPosition,
            'P' => $preparePosition,
        ];
    }

    public function commitPosition(): ?int
    {
        switch ($this->mode) {
            case self::Position:
                return $this->checkpoint['C'];
            default:
                return null;
        }
    }

    public function preparePosition(): ?int
    {
        switch ($this->mode) {
            case self::Position:
                return $this->checkpoint['P'];
            default:
                return null;
        }
    }

    public function streamPosition(string $stream): ?int
    {
        switch ($this->mode) {
            case self::Stream:
            case self::MultiStream:
                return $this->checkpoint['$s'][$stream] ?? null;
            default:
                return null;
        }
    }

    public function streamName(): ?string
    {
        switch ($this->mode) {
            case self::Stream:
                return \current(\array_keys($this->checkpoint['$s']));
            default:
                return null;
        }
    }

    public function streamNames(): ?array
    {
        switch ($this->mode) {
            case self::Stream:
                return null;
            case self::MultiStream:
                return \array_keys($this->checkpoint['$s']);
            default:
                return null;
        }
    }

    public function equals(CheckpointTag $other): bool
    {
        return $this->mode === $other->mode
            && $this->checkpoint === $other->checkpoint;
    }

    public function toArray(): array
    {
        return $this->checkpoint;
    }

    public function toJsonString(): string
    {
        return \json_encode($this->checkpoint);
    }
}
