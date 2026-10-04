<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

/**
 * Builder for constructing StatusResult objects.
 */
final class StatusResultBuilder
{
    private string $manifestState = StatusResult::MANIFEST_UNKNOWN;
    private bool $backupExists = false;
    /** @var array<int, string> */
    private array $peelSections = [];
    private ?string $packageVersion = null;
    /** @var array<int, ApplicationVersionSource> */
    private array $applicationVersionSources = [];
    private string $versionConsistency = StatusResult::VERSION_UNKNOWN;
    private ?string $canonicalApplicationVersion = null;
    private ?string $latestGitTag = null;
    private ?string $changelogVersion = null;
    private bool $workingTreeClean = true;
    /** @var array<int, string> */
    private array $workingTreeChanges = [];
    private string $releaseState = StatusResult::RELEASE_NOT_READY;
    /** @var array<int, string> */
    private array $issues = [];

    public function manifestState(string $state): self
    {
        $this->manifestState = $state;
        return $this;
    }

    public function backupExists(bool $exists): self
    {
        $this->backupExists = $exists;
        return $this;
    }

    /**
     * @param array<int, string> $sections
     */
    public function peelSections(array $sections): self
    {
        $this->peelSections = $sections;
        return $this;
    }

    public function packageVersion(?string $version): self
    {
        $this->packageVersion = $version;
        return $this;
    }

    /**
     * @param array<int, ApplicationVersionSource> $sources
     */
    public function applicationVersionSources(array $sources): self
    {
        $this->applicationVersionSources = $sources;
        return $this;
    }

    public function versionConsistency(string $consistency): self
    {
        $this->versionConsistency = $consistency;
        return $this;
    }

    public function canonicalApplicationVersion(?string $version): self
    {
        $this->canonicalApplicationVersion = $version;
        return $this;
    }

    public function latestGitTag(?string $tag): self
    {
        $this->latestGitTag = $tag;
        return $this;
    }

    public function changelogVersion(?string $version): self
    {
        $this->changelogVersion = $version;
        return $this;
    }

    public function workingTreeClean(bool $clean): self
    {
        $this->workingTreeClean = $clean;
        return $this;
    }

    /**
     * @param array<int, string> $changes
     */
    public function workingTreeChanges(array $changes): self
    {
        $this->workingTreeChanges = $changes;
        return $this;
    }

    public function releaseState(string $state): self
    {
        $this->releaseState = $state;
        return $this;
    }

    /**
     * @param array<int, string> $issues
     */
    public function issues(array $issues): self
    {
        $this->issues = $issues;
        return $this;
    }

    public function build(): StatusResult
    {
        return new StatusResult(
            manifestState: $this->manifestState,
            backupExists: $this->backupExists,
            peelSections: $this->peelSections,
            packageVersion: $this->packageVersion,
            applicationVersionSources: $this->applicationVersionSources,
            versionConsistency: $this->versionConsistency,
            canonicalApplicationVersion: $this->canonicalApplicationVersion,
            latestGitTag: $this->latestGitTag,
            changelogVersion: $this->changelogVersion,
            workingTreeClean: $this->workingTreeClean,
            workingTreeChanges: $this->workingTreeChanges,
            releaseState: $this->releaseState,
            issues: $this->issues,
        );
    }
}
