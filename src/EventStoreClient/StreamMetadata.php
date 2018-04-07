<?php

declare(strict_types=1);

namespace Prooph\EventStoreClient;

class StreamMetadata
{
    /**
     * The maximum number of events allowed in the stream.
     * @var int|null
     */
    private $maxCount;

    /**
     * The maximum age in seconds for events allowed in the stream.
     * @var int|null
     */
    private $maxAge;

    /**
     * The event number from which previous events can be scavenged.
     * This is used to implement soft-deletion of streams.
     * @var int|null
     */
    private $truncateBefore;

    /**
     * The amount of time in seconds for which the stream head is cachable.
     * @var int|null
     */
    private $cacheControl;

    /**
     * The access control list for the stream.
     * @var StreamAcl|null
     */
    private $acl;

    /**
     * key => value pairs of custom metadata
     * @var array
     */
    private $customMetadata;

    public function __construct(
        ?int $maxCount,
        ?int $maxAge,
        ?int $truncateBefore,
        ?int $cacheControl,
        ?StreamAcl $acl,
        array $customMetadata = []
    ) {
        if (null !== $maxCount && $maxCount <= 0) {
            throw new \InvalidArgumentException('maxCount should be positive value');
        }

        if (null !== $maxAge && $maxAge < 1) {
            throw new \InvalidArgumentException('maxAge should be positive value');
        }

        if (null !== $truncateBefore && $truncateBefore < 0) {
            throw new \InvalidArgumentException('truncateBefore should be non-negative value');
        }

        if (null !== $cacheControl && $cacheControl < 1) {
            throw new \InvalidArgumentException('cacheControl should be positive value');
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

    public function maxAge(): ?int
    {
        return $this->maxAge;
    }

    public function truncateBefore(): ?int
    {
        return $this->truncateBefore;
    }

    public function cacheControl(): ?int
    {
        return $this->cacheControl;
    }

    public function acl(): ?StreamAcl
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

    public function toArray(): array
    {
        $data = [];

        if (null !== $this->maxCount) {
            $data['$maxCount'] = $this->maxCount;
        }

        if (null !== $this->maxAge) {
            $data['$maxAge'] = $this->maxAge;
        }

        if (null !== $this->truncateBefore) {
            $data['$truncateBefore'] = $this->truncateBefore;
        }

        if (null !== $this->cacheControl) {
            $data['$cacheControl'] = $this->cacheControl;
        }

        if (null !== $this->acl) {
            $data['$acl'] = $this->acl->toArray();
        }

        foreach ($this->customMetadata as $key => $value) {
            $data[$key] = $value;
        }

        return $data;
    }

    public static function fromArray(array $data): StreamMetadata
    {
        $internal = [
            '$maxCount',
            '$maxAge',
            '$truncateBefore',
            '$cacheControl',
        ];

        $params = [];

        foreach ($data as $key => $value) {
            if (in_array($key, $internal, true)) {
                $params[$key] = $value;
            } elseif ($key === '$acl') {
                $params['$acl'] = StreamAcl::fromArray($value);
            } else {
                $params['customMetadata'][$key] = $value;
            }
        }

        return new self(
            $params['$maxCount'] ?? null,
            $params['$maxAge'] ?? null,
            $params['$truncateBefore'] ?? null,
            $params['$cacheControl'] ?? null,
            $params['$acl'] ?? null,
            $params['customMetadata'] ?? []
        );
    }
}
