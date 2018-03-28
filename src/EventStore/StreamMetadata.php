<?php

declare(strict_types=1);

namespace Prooph\EventStore;

final class StreamMetadata
{
    /**
     * The maximum number of events allowed in the stream.
     * @var int|null
     */
    private $maxCount;

    /**
     * The maximum age of events allowed in the stream.
     * @var \DateInterval|null
     */
    private $maxAge;

    /**
     * The event number from which previous events can be scavenged.
     * This is used to implement soft-deletion of streams.
     * @var int|null
     */
    private $truncateBefore;

    /**
     * The amount of time for which the stream head is cachable.
     * @var \DateInterval|null
     */
    private $cacheControl;

    /**
     * The access control list for the stream.
     * @var StreamAcl
     */
    private $acl;

    /**
     * key => value pairs of custom metadata
     * @var array
     */
    private $customMetadata;

    public function __construct(
        ?int $maxCount,
        ?\DateInterval $maxAge,
        ?int $truncateBefore,
        ?\DateInterval $cacheControl,
        StreamAcl $acl,
        array $customMetadata = []
    ) {
        if (null !== $maxCount && $maxCount <= 0) {
            throw new \InvalidArgumentException('maxCount should be positive value');
        }

        if (null !== $maxAge && 1 === $maxAge->invert) {
            throw new \InvalidArgumentException('maxAge should be positive time span');
        }

        if (null !== $truncateBefore && $truncateBefore < 0) {
            throw new \InvalidArgumentException('truncateBefore should be non-negative value');
        }

        if (null !== $cacheControl && 1 === $cacheControl->invert) {
            throw new \InvalidArgumentException('cacheControl should be positive time span');
        }

        $this->maxCount = $maxCount;
        $this->maxAge = $maxAge;
        $this->truncateBefore = $truncateBefore;
        $this->cacheControl = $cacheControl;
        $this->acl = $acl;
        $this->customMetadata = $customMetadata;
    }

    public function maxCount(): ?int
    {
        return $this->maxCount;
    }

    public function maxAge(): ?\DateInterval
    {
        return $this->maxAge;
    }

    public function truncateBefore(): ?int
    {
        return $this->truncateBefore;
    }

    public function cacheControl(): ?\DateInterval
    {
        return $this->cacheControl;
    }

    public function acl(): StreamAcl
    {
        return $this->acl;
    }

    /**
     * @return array
     */
    public function customMetadata(): array
    {
        return $this->customMetadata;
    }

    /**
     * @param string $key
     * @return mixed
     */
    public function getValue(string $key)
    {
        if (! isset($this->customMetadata[$key])) {
            throw new \InvalidArgumentException('Key ' . $key . ' not found in custom metadata');
        }

        return $this->customMetadata[$key];
    }
}
