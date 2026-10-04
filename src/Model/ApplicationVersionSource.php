<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

/**
 * Represents a single discovered application version source.
 */
final class ApplicationVersionSource
{
    public function __construct(
        public readonly string $path,
        public readonly string $version,
    ) {}

    /**
     * Returns the version without a leading 'v' prefix for comparison.
     */
    public function normalizedVersion(): string
    {
        return ltrim($this->version, 'v');
    }
}
