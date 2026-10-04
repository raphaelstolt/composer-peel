<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

/**
 * Inspects the current repository and returns a StatusResult describing the composer-peel state.
 *
 * This class is read-only and does not modify any files, Git state, commits, or tags.
 */
class StatusChecker
{
    private Configuration $configuration;
    private string $workingDirectory;

    public function __construct(?Configuration $configuration = null, ?string $workingDirectory = null)
    {
        $this->configuration = $configuration ?? new Configuration();
        $this->workingDirectory = $workingDirectory ?? (string) getcwd();
    }

    public function setConfiguration(Configuration $configuration): void
    {
        $this->configuration = $configuration;
    }

    public function setWorkingDirectory(string $workingDirectory): void
    {
        $this->workingDirectory = $workingDirectory;
    }

    public function check(): StatusResult
    {
        $issues = [];

        $manifestState = $this->detectManifestState();
        $backupExists = $this->backupExists();
        $packageVersion = $this->getPackageVersion();
        $appVersionSources = $this->detectApplicationVersionSources();
        [$versionConsistency, $canonicalAppVersion] = $this->evaluateVersionConsistency($appVersionSources, $issues);
        $latestGitTag = $this->getLatestGitTag();
        $changelogVersion = $this->getChangelogVersion();
        [$workingTreeClean, $workingTreeChanges] = $this->inspectWorkingTree();

        $this->collectAppVersionIssues($appVersionSources, $issues);
        $this->collectGitTagIssues($canonicalAppVersion, $latestGitTag, $issues);
        $this->collectChangelogIssues($canonicalAppVersion, $changelogVersion, $issues);
        $this->collectWorkingTreeIssues($workingTreeClean, $workingTreeChanges, $issues);

        $releaseState = $this->determineReleaseState($issues);

        return StatusResult::create()
            ->manifestState($manifestState)
            ->backupExists($backupExists)
            ->peelSections($this->configuration->getPeelSections())
            ->packageVersion($packageVersion)
            ->applicationVersionSources($appVersionSources)
            ->versionConsistency($versionConsistency)
            ->canonicalApplicationVersion($canonicalAppVersion)
            ->latestGitTag($latestGitTag)
            ->changelogVersion($changelogVersion)
            ->workingTreeClean($workingTreeClean)
            ->workingTreeChanges($workingTreeChanges)
            ->releaseState($releaseState)
            ->issues($issues)
            ->build();
    }

    private function backupExists(): bool
    {
        return file_exists($this->workingDirectory . DIRECTORY_SEPARATOR . $this->configuration->getBackupPath());
    }

    private function determineReleaseState(array $issues): string
    {
        if (count($issues) === 0) {
            return StatusResult::RELEASE_READY;
        }

        return StatusResult::RELEASE_NOT_READY;
    }

    /**
     * Detects whether the current composer.json is peeled, unpeeled, or modified.
     */
    private function detectManifestState(): string
    {
        $manifestPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $backupPath = $this->workingDirectory . DIRECTORY_SEPARATOR . $this->configuration->getBackupPath();

        if (!file_exists($manifestPath)) {
            return StatusResult::MANIFEST_UNKNOWN;
        }

        $manifest = $this->decodeManifest($manifestPath);
        if ($manifest === null) {
            return StatusResult::MANIFEST_UNKNOWN;
        }

        // If no backup exists, we check if the configured sections are present
        if (!file_exists($backupPath)) {
            $backupManifest = null;
            // Check if any peel sections are present in the manifest
            $hasPeelSections = false;
            foreach ($this->configuration->getPeelSections() as $section) {
                if (array_key_exists($section, $manifest)) {
                    $hasPeelSections = true;
                    break;
                }
            }

            return $hasPeelSections ? StatusResult::MANIFEST_UNPEELED : StatusResult::MANIFEST_PEELED;
        }

        $backupManifest = $this->decodeManifest($backupPath);
        if ($backupManifest === null) {
            return StatusResult::MANIFEST_UNKNOWN;
        }

        $comparator = new ManifestComparator($this->configuration);
        $state = $comparator->compare($manifest, $backupManifest);

        return match ($state) {
            ManifestComparator::PEELED => StatusResult::MANIFEST_PEELED,
            ManifestComparator::RESTORED => StatusResult::MANIFEST_UNPEELED,
            default => StatusResult::MANIFEST_MODIFIED,
        };
    }

    /**
     * Extracts the package version from composer.json (not an application version source).
     */
    private function getPackageVersion(): ?string
    {
        $manifestPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        $manifest = $this->decodeManifest($manifestPath);

        if ($manifest === null || !array_key_exists('version', $manifest)) {
            return null;
        }

        $version = $manifest['version'];
        if (!is_string($version) || trim($version) === '') {
            return null;
        }

        return $version;
    }

    /**
     * Discovers application version sources following the same rules as version-aligner.
     *
     * @return array<int, ApplicationVersionSource>
     */
    private function detectApplicationVersionSources(): array
    {
        $sources = [];
        $filesToCheck = $this->getApplicationVersionFiles();

        foreach ($filesToCheck as $fileToCheck) {
            $filePath = $this->workingDirectory . DIRECTORY_SEPARATOR . $fileToCheck;
            if (!file_exists($filePath)) {
                continue;
            }

            $content = (string) file_get_contents($filePath);
            if (preg_match('/[\'"](v?\d+\.\d+\.\d+(?:-[a-zA-Z0-9\.]+)*)[\'"]/', $content, $matches)) {
                $sources[] = new ApplicationVersionSource($fileToCheck, $matches[1]);
            }
        }

        return $sources;
    }

    /**
     * Returns the list of files to check for application version, following version-aligner semantics.
     *
     * @return array<int, string>
     */
    private function getApplicationVersionFiles(): array
    {
        $filesToCheck = [
            'src/Console/Application.php',
            'src/Application.php',
            'src/Server.php',
        ];

        // Check composer.json for bin files and prepend them
        $composerJsonPath = $this->workingDirectory . DIRECTORY_SEPARATOR . 'composer.json';
        if (file_exists($composerJsonPath)) {
            $manifest = $this->decodeManifest($composerJsonPath);
            if (is_array($manifest) && isset($manifest['bin']) && is_array($manifest['bin'])) {
                foreach ($manifest['bin'] as $binFile) {
                    if (is_string($binFile)) {
                        array_unshift($filesToCheck, $binFile);
                    }
                }
            }
        }

        return array_unique($filesToCheck);
    }

    /**
     * Evaluates version consistency across all discovered application version sources.
     *
     * @param array<int, ApplicationVersionSource> $sources
     * @return array{0: string, 1: string|null} [consistency, canonicalVersion]
     */
    private function evaluateVersionConsistency(array $sources, array &$issues): array
    {
        if (count($sources) === 0) {
            return [StatusResult::VERSION_UNKNOWN, null];
        }

        if (count($sources) === 1) {
            return [StatusResult::VERSION_CONSISTENT, $sources[0]->normalizedVersion()];
        }

        // Multiple sources - check if they all agree
        $normalizedVersions = array_map(fn($s) => $s->normalizedVersion(), $sources);
        $uniqueVersions = array_unique($normalizedVersions);

        if (count($uniqueVersions) === 1) {
            return [StatusResult::VERSION_CONSISTENT, reset($uniqueVersions)];
        }

        // Inconsistent versions
        $canonicalVersion = null;
        foreach ($sources as $source) {
            $issues[] = sprintf(
                '%s contains version %s but the other application version source contains %s',
                $source->path,
                $source->version,
                $this->getOtherVersion($source, $sources),
            );
        }

        // Use the most common version as canonical
        $versionCounts = array_count_values($normalizedVersions);
        arsort($versionCounts);
        $canonicalVersion = key($versionCounts);

        return [StatusResult::VERSION_INCONSISTENT, $canonicalVersion];
    }

    /**
     * @param array<int, ApplicationVersionSource> $sources
     */
    private function getOtherVersion(ApplicationVersionSource $source, array $sources): string
    {
        foreach ($sources as $s) {
            if ($s->path !== $source->path && $s->normalizedVersion() !== $source->normalizedVersion()) {
                return $s->version;
            }
        }
        return '?';
    }

    /**
     * Returns the latest Git tag, or null if no tags exist.
     */
    private function getLatestGitTag(): ?string
    {
        $output = [];
        $returnCode = 0;
        exec(
            sprintf('cd %s && git describe --tags --abbrev=0 2>/dev/null', escapeshellarg($this->workingDirectory)),
            $output,
            $returnCode,
        );

        if ($returnCode === 0 && isset($output[0])) {
            return trim($output[0]);
        }

        return null;
    }

    /**
     * Returns the latest CHANGELOG version, or null if not found.
     */
    private function getChangelogVersion(): ?string
    {
        $changelogPath = null;
        $files = scandir($this->workingDirectory) ?: [];

        foreach ($files as $file) {
            $path = $this->workingDirectory . DIRECTORY_SEPARATOR . $file;
            if (
                is_file($path)
                && preg_match('/^(?:CHANGELOG|CHANGES|HISTORY|RELEASE(?:_NOTES)?)(?:\.md|\.txt|\.rst)?$/i', $file)
            ) {
                $changelogPath = $path;
                break;
            }
        }

        if ($changelogPath === null) {
            return null;
        }

        $content = file_get_contents($changelogPath);
        if ($content === false) {
            return null;
        }

        if (preg_match(
            '/^## \[?(v?\d+\.\d+\.\d+(?:-[a-zA-Z0-9\.]+)*)\]?(?: - \d{4}-\d{2}-\d{2})?$/m',
            $content,
            $matches,
        )) {
            return $matches[1];
        }

        return null;
    }

    /**
     * Inspects the Git working tree for changes.
     *
     * @return array{0: bool, 1: array<int, string>} [isClean, changes]
     */
    private function inspectWorkingTree(): array
    {
        $output = [];
        $returnCode = 0;
        exec(
            sprintf(
                'cd %s && git status --porcelain --untracked-files=all 2>/dev/null',
                escapeshellarg($this->workingDirectory),
            ),
            $output,
            $returnCode,
        );

        if ($returnCode !== 0 || count($output) === 0) {
            return [true, []];
        }

        $changes = [];
        foreach ($output as $line) {
            $changes[] = trim($line);
        }

        // Filter out expected changes (composer.json, backup, managed files)
        // so the working tree is only considered dirty when there are unexpected changes
        $unexpectedChanges = $this->filterUnexpectedChanges($changes);

        return [count($unexpectedChanges) === 0, $unexpectedChanges];
    }

    /**
     * @param array<int, ApplicationVersionSource> $appVersionSources
     * @param array<int, string> $issues
     */
    private function collectAppVersionIssues(array $appVersionSources, array &$issues): void
    {
        if (count($appVersionSources) === 0) {
            $issues[] = 'No supported application version source was found';
        }
    }

    /**
     * @param array<int, string> $issues
     */
    private function collectGitTagIssues(?string $canonicalAppVersion, ?string $latestGitTag, array &$issues): void
    {
        if ($canonicalAppVersion === null || $latestGitTag === null) {
            return;
        }

        $normalizedTag = ltrim($latestGitTag, 'v');
        if ($normalizedTag === $canonicalAppVersion) {
            return;
        }

        $issues[] = sprintf(
            'Application version %s does not match the latest Git tag %s',
            $canonicalAppVersion,
            $latestGitTag,
        );
    }

    /**
     * @param array<int, string> $issues
     */
    private function collectChangelogIssues(
        ?string $canonicalAppVersion,
        ?string $changelogVersion,
        array &$issues,
    ): void {
        if ($canonicalAppVersion === null || $changelogVersion === null) {
            return;
        }

        $normalizedChangelog = ltrim($changelogVersion, 'v');
        if ($normalizedChangelog === $canonicalAppVersion) {
            return;
        }

        $issues[] = sprintf(
            'Application version %s does not match the CHANGELOG version %s',
            $canonicalAppVersion,
            $changelogVersion,
        );
    }

    /**
     * @param array<int, string> $workingTreeChanges
     * @param array<int, string> $issues
     */
    private function collectWorkingTreeIssues(bool $workingTreeClean, array $workingTreeChanges, array &$issues): void
    {
        if ($workingTreeClean) {
            return;
        }

        if (count($workingTreeChanges) > 0) {
            $issues[] = 'Working tree contains unexpected changes';
        }
    }

    /**
     * Filters working tree changes to find unexpected ones.
     *
     * @param array<int, string> $workingTreeChanges
     * @return array<int, string>
     */
    private function filterUnexpectedChanges(array $workingTreeChanges): array
    {
        $allowedPaths = array_merge([
            'composer.json',
            $this->configuration->getBackupPath(),
        ], $this->configuration->getManagedFiles());

        $unexpected = [];
        foreach ($workingTreeChanges as $change) {
            $path = ltrim(substr(trim($change), 2));
            if ($this->isPathAllowed($path, $allowedPaths)) {
                continue;
            }
            $unexpected[] = $change;
        }

        return $unexpected;
    }

    /**
     * @param array<int, string> $allowedPaths
     */
    private function isPathAllowed(string $path, array $allowedPaths): bool
    {
        foreach ($allowedPaths as $allowedPath) {
            if ($path === $allowedPath || $path === ltrim($allowedPath, './')) {
                return true;
            }
            if (str_ends_with($allowedPath, '/') && str_starts_with($path, $allowedPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decodeManifest(string $path): ?array
    {
        if (!file_exists($path)) {
            return null;
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return null;
        }

        $manifest = json_decode($content, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
            return null;
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
