<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ReleaseManager
{
    private Configuration $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
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
        $this->verifyOnlyManifestChanged();

        $this->executeGitCommand(['git', 'add', 'composer.json']);
        $this->executeGitCommand(['git', 'commit', '-m', $this->configuration->getBeforeTagCommitMessage()]);

        $this->executeGitCommand(['git', 'tag', $tag]);
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

    private function verifyOnlyManifestChanged(): void
    {
        exec('git status --porcelain --untracked-files=all', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('The composer-peel release workflow requires a clean working tree.');
        }

        $allowedPaths = ['composer.json', $this->configuration->getBackupPath()];
        foreach ($output as $line) {
            if (!in_array(substr($line, 3), $allowedPaths, true)) {
                throw new RuntimeException('The composer-peel release workflow requires a clean working tree.');
            }
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

    public function commitRestoredManifest(): void
    {
        $this->verifyGitIsAvailable();

        $this->executeGitCommand(['git', 'add', 'composer.json']);
        $this->executeGitCommand(['git', 'commit', '-m', $this->configuration->getAfterTagCommitMessage()]);
    }
}
