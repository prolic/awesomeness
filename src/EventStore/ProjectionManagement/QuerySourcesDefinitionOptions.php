<?php

declare(strict_types=1);

namespace Prooph\EventStore\ProjectionManagement;

class QuerySourcesDefinitionOptions
{
    /** @var bool|null */
    private $definesStateTransform;
    /** @var bool|null */
    private $definesCatalogTransform;
    /** @var bool|null */
    private $producesResults;
    /** @var bool|null */
    private $definesFold;
    /** @var bool|null */
    private $handlesDeletedNotifications;
    /** @var bool|null */
    private $includeLinksOption;
    /** @var bool|null */
    private $disableParallelismOption;
    /** @var string|null */
    private $resultStreamNameOption;
    /** @var string|null */
    private $partitionResultStreamNamePatternOption;
    /** @var bool|null */
    private $reorderEventsOption;
    /** @var int|null */
    private $processingLagOption;
    /** @var bool|null */
    private $isBiState;

    public function __construct(
        bool $definesStateTransform = null,
        bool $definesCatalogTransform = null,
        bool $producesResults = null,
        bool $definesFold = null,
        bool $handlesDeletedNotifications = null,
        bool $includeLinksOption = null,
        bool $disableParallelismOption = null,
        string $resultStreamNameOption = null,
        string $partitionResultStreamNamePatternOption = null,
        bool $reorderEventsOption = null,
        int $processingLagOption = null,
        bool $isBiState = null
    ) {
        $this->definesStateTransform = $definesStateTransform;
        $this->definesCatalogTransform = $definesCatalogTransform;
        $this->producesResults = $producesResults;
        $this->definesFold = $definesFold;
        $this->handlesDeletedNotifications = $handlesDeletedNotifications;
        $this->includeLinksOption = $includeLinksOption;
        $this->disableParallelismOption = $disableParallelismOption;
        $this->resultStreamNameOption = $resultStreamNameOption;
        $this->partitionResultStreamNamePatternOption = $partitionResultStreamNamePatternOption;
        $this->reorderEventsOption = $reorderEventsOption;
        $this->processingLagOption = $processingLagOption;
        $this->isBiState = $isBiState;
    }

    public static function fromArray(array $options): QuerySourcesDefinitionOptions
    {
        return new self(
            $options['definesStateTransform'] ?? null,
            $options['definesCatalogTransform'] ?? null,
            $options['producesResults'] ?? null,
            $options['definesFold'] ?? null,
            $options['handlesDeletedNotifications'] ?? null,
            $options['includeLinksOption'] ?? null,
            $options['disableParallelismOption'] ?? null,
            $options['resultStreamNameOption'] ?? null,
            $options['partitionResultStreamNamePatternOption'] ?? null,
            $options['reorderEventsOption'] ?? null,
            $options['processingLagOption'] ?? null,
            $options['isBiState'] ?? null
        );
    }

    public function definesStateTransform(): ?bool
    {
        return $this->definesStateTransform;
    }

    public function definesCatalogTransform(): ?bool
    {
        return $this->definesCatalogTransform;
    }

    public function producesResults(): ?bool
    {
        return $this->producesResults;
    }

    public function definesFold(): ?bool
    {
        return $this->definesFold;
    }

    public function handlesDeletedNotifications(): ?bool
    {
        return $this->handlesDeletedNotifications;
    }

    public function includeLinksOption(): ?bool
    {
        return $this->includeLinksOption;
    }

    public function disableParallelismOption(): ?bool
    {
        return $this->disableParallelismOption;
    }

    public function resultStreamNameOption(): ?string
    {
        return $this->resultStreamNameOption;
    }

    public function partitionResultStreamNamePatternOption(): ?string
    {
        return $this->partitionResultStreamNamePatternOption;
    }

    public function reorderEventsOption(): ?bool
    {
        return $this->reorderEventsOption;
    }

    public function processingLagOption(): ?int
    {
        return $this->processingLagOption;
    }

    public function isBiState(): ?bool
    {
        return $this->isBiState;
    }

    public function toArray(): array
    {
        $options = [];

        if (null !== $this->definesStateTransform) {
            $options['definesStateTransform'] = $this->definesStateTransform;
        }

        if (null !== $this->definesCatalogTransform) {
            $options['definesCatalogTransform'] = $this->definesCatalogTransform;
        }

        if (null !== $this->producesResults) {
            $options['producesResults'] = $this->producesResults;
        }

        if (null !== $this->definesFold) {
            $options['definesFold'] = $this->definesFold;
        }

        if (null !== $this->handlesDeletedNotifications) {
            $options['handlesDeletedNotifications'] = $this->handlesDeletedNotifications;
        }

        if (null !== $this->includeLinksOption) {
            $options['includeLinksOption'] = $this->includeLinksOption;
        }

        if (null !== $this->disableParallelismOption) {
            $options['disableParallelismOption'] = $this->disableParallelismOption;
        }

        if (null !== $this->resultStreamNameOption) {
            $options['resultStreamNameOption'] = $this->resultStreamNameOption;
        }

        if (null !== $this->partitionResultStreamNamePatternOption) {
            $options['partitionResultStreamNamePatternOption'] = $this->partitionResultStreamNamePatternOption;
        }

        if (null !== $this->reorderEventsOption) {
            $options['reorderEventsOption'] = $this->reorderEventsOption;
        }

        if (null !== $this->processingLagOption) {
            $options['processingLagOption'] = $this->processingLagOption;
        }

        if (null !== $this->isBiState) {
            $options['isBiState'] = $this->isBiState;
        }

        return $options;
    }
}
