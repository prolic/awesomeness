<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient\Data;

use Prooph\EventStoreClient\Exception\InvalidArgumentException;
use Prooph\EventStoreClient\Internal\Consts;

class CatchUpSubscriptionSettings
{
    /**
     * The maximum amount of events to cache when processing from a live subscription.
     * Going above this value will drop the subscription.
     *
     * @var int
     */
    private $maxLiveQueueSize;

    /**
     * The number of events to read per batch when reading the history.
     *
     * @var int
     */
    private $readBatchSize;

    /** @var bool */
    private $resolveLinkTos;

    /** @var string */
    private $subscriptionName;

    public function __construct(int $maxLiveQueueSize, int $readBatchSize, bool $resolveLinkTos, string $subscriptionName)
    {
        if ($readBatchSize < 1) {
            throw new InvalidArgumentException('Read batch size must be positive');
        }

        if ($maxLiveQueueSize < 1) {
            throw new InvalidArgumentException('Max live queue size must be positive');
        }

        if ($readBatchSize > Consts::MaxReadSize) {
            throw new InvalidArgumentException(\sprintf(
                'Read batch size should be less than \'%s\'. For larger reads you should page',
                Consts::MaxReadSize
            ));
        }

        $this->maxLiveQueueSize = $maxLiveQueueSize;
        $this->readBatchSize = $readBatchSize;
        $this->resolveLinkTos = $resolveLinkTos;
        $this->subscriptionName = $subscriptionName;
    }

    public static function default(): self
    {
        return new self(
            Consts::CatchUpDefaultMaxPushQueueSize,
            Consts::CatchUpDefaultReadBatchSize,
            true,
            ''
        );
    }

    public function maxLiveQueueSize(): int
    {
        return $this->maxLiveQueueSize;
    }

    public function readBatchSize(): int
    {
        return $this->readBatchSize;
    }

    public function resolveLinkTos(): bool
    {
        return $this->resolveLinkTos;
    }

    public function subscriptionName(): string
    {
        return $this->subscriptionName;
    }
}
