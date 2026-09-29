<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ReleaseManager
{
    private ComposerPeeler $peeler;
    private Configuration $configuration;

    public function __construct(ComposerPeeler $peeler, Configuration $configuration)
    {
        $this->peeler = $peeler;
        $this->configuration = $configuration;
        $this->peeler->setConfiguration($this->configuration);
    }

    public function release(string $tag): void
    {
        if (!$this->isValidSemver($tag)) {
            throw new RuntimeException("The provided Git tag '{$tag}' is not a valid semantic version.");
        }

        if (!$this->configuration->isBackupEnabled()) {
            throw new RuntimeException('Release workflow requires backup to be enabled.');
        }

        $this->verifyGitIsAvailable();
        $this->verifyCleanWorkingTree();

        $this->peeler->peel();

        $this->executeGitCommand(['git', 'add', 'composer.json']);
        $this->executeGitCommand(['git', 'commit', '-m', $this->configuration->getBeforeTagCommitMessage()]);

        $this->executeGitCommand(['git', 'tag', $tag]);

        $this->restoreManifest();

        $this->executeGitCommand(['git', 'add', 'composer.json']);
        $this->executeGitCommand(['git', 'commit', '-m', $this->configuration->getAfterTagCommitMessage()]);
    }

    private function isValidSemver(string $tag): bool
    {
        $regex = '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-zA-Z0-9-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-zA-Z0-9-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';
        return preg_match($regex, $tag) === 1;
    }

    private function verifyGitIsAvailable(): void
    {
        exec('git --version', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('Git is not available or not installed.');
        }
    }

    private function verifyCleanWorkingTree(): void
    {
        exec('git status --porcelain', $output, $resultCode);
        if ($resultCode !== 0 || count($output) > 0) {
            throw new RuntimeException("ERROR: Working tree contains uncommitted changes.\n\ncomposer-peel release must start from a clean working tree.");
        }
    }

    /**
     * @param array<int, string> $command
     */
    private function executeGitCommand(array $command): void
    {
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));
        exec($escapedCommand . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            $errorMessage = implode("\n", $output);
            throw new RuntimeException("Git command failed: {$escapedCommand}\nError: {$errorMessage}");
        }
    }

    private function restoreManifest(): void
    {
        $backupPath = $this->configuration->getBackupPath();
        $manifestPath = getcwd() . '/composer.json';

        if (!file_exists($backupPath)) {
            throw new RuntimeException("Backup file not found at: {$backupPath}");
        }

        if (copy($backupPath, $manifestPath) === false) {
            throw new RuntimeException('Failed to restore composer.json from backup.');
        }
    }
}
