<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

final class DryRunResult
{
    /**
     * @param array<int, string> $removedSections
     */
    public function __construct(
        private array $removedSections,
        private int $originalSize,
        private int $projectedSize,
        private string $originalContent = '',
        private string $projectedContent = '',
    ) {}

    /**
     * @return array<int, string>
     */
    public function getRemovedSections(): array
    {
        return $this->removedSections;
    }

    public function getOriginalSize(): int
    {
        return $this->originalSize;
    }

    public function getProjectedSize(): int
    {
        return $this->projectedSize;
    }

    public function getOriginalContent(): string
    {
        return $this->originalContent;
    }

    public function getProjectedContent(): string
    {
        return $this->projectedContent;
    }
}
