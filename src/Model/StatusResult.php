<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

/**
 * Holds the complete status result for a composer-peel status check.
 */
final class StatusResult
{
    public const MANIFEST_PEELED = 'peeled';
    public const MANIFEST_UNPEELED = 'unpeeled';
    public const MANIFEST_MODIFIED = 'modified';
    public const MANIFEST_UNKNOWN = 'unknown';

    public const VERSION_CONSISTENT = 'consistent';
    public const VERSION_INCONSISTENT = 'inconsistent';
    public const VERSION_UNKNOWN = 'unknown';

    public const RELEASE_READY = 'ready';
    public const RELEASE_NOT_READY = 'not ready';

    public function __construct(
        public readonly string $manifestState,
        public readonly bool $backupExists,
        /** @var array<int, string> */
        public readonly array $peelSections,
        public readonly ?string $packageVersion,
        /** @var array<int, ApplicationVersionSource> */
        public readonly array $applicationVersionSources,
        public readonly string $versionConsistency,
        public readonly ?string $canonicalApplicationVersion,
        public readonly ?string $latestGitTag,
        public readonly ?string $changelogVersion,
        public readonly bool $workingTreeClean,
        /** @var array<int, string> */
        public readonly array $workingTreeChanges,
        public readonly string $releaseState,
        /** @var array<int, string> */
        public readonly array $issues,
    ) {}

    /**
     * Determines whether the repository is ready for release.
     */
    public function isReleaseReady(): bool
    {
        return $this->releaseState === self::RELEASE_READY;
    }

    /**
     * Creates a new StatusResultBuilder.
     */
    public static function create(): StatusResultBuilder
    {
        return new StatusResultBuilder();
    }
}
