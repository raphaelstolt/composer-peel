<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ReleaseManager
{
    private Configuration $configuration;
    private ReleaseVersionValidator $validator;

    public function __construct(Configuration $configuration, ?ReleaseVersionValidator $validator = null)
    {
        $this->configuration = $configuration;
        $this->validator = $validator ?? new ReleaseVersionValidator();
    }

    public function release(string $tag): void
    {
        $this->validator->validate($tag);

        if (!$this->configuration->isBackupEnabled()) {
            throw new RuntimeException('Release workflow requires backup to be enabled.');
        }

        $this->verifyGitIsAvailable();
        $filesToCommit = $this->getReleaseFilesToCommit();
        $this->verifyManifestIsPeeled();

        $this->executeGitCommand(array_merge(['git', 'add'], $filesToCommit));
        $this->executeGitCommand(['git', 'commit', '-m', $this->configuration->getBeforeTagCommitMessage()]);

        $this->executeGitCommand(['git', 'tag', $tag]);
    }

    private function verifyGitIsAvailable(): void
    {
        exec('git --version', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('Git is not available or not installed.');
        }
    }

    /**
     * @return array<int, string>
     */
    private function getReleaseFilesToCommit(): array
    {
        exec('git status --porcelain --untracked-files=all', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('The composer-peel release workflow requires a clean working tree.');
        }

        $allowedPaths = ['composer.json', $this->configuration->getBackupPath(), 'CHANGELOG.md'];
        $filesToCommit = ['composer.json'];

        foreach ($output as $line) {
            $path = substr($line, 3);
            if (in_array($path, $allowedPaths, true) || str_starts_with($path, 'bin/')) {
                if ($path !== $this->configuration->getBackupPath() && $path !== 'composer.json') {
                    $filesToCommit[] = $path;
                }
            } else {
                throw new RuntimeException('The composer-peel release workflow requires a clean working tree.');
            }
        }

        return $filesToCommit;
    }

    private function verifyManifestIsPeeled(): void
    {
        $backupPath = $this->configuration->getBackupPath();

        if (!file_exists($backupPath)) {
            throw new RuntimeException(
                "Backup file {$backupPath} does not exist. Run the peel command before releasing.",
            );
        }

        $manifest = $this->decodeManifest(getcwd() . '/composer.json', 'composer.json');
        $backupManifest = $this->decodeManifest($backupPath, "Backup file {$backupPath}");

        $state = (new ManifestComparator($this->configuration))->compare($manifest, $backupManifest);

        if ($state === ManifestComparator::RESTORED) {
            throw new RuntimeException('composer.json has not been peeled. Run the peel command before releasing.');
        }

        if ($state === ManifestComparator::MODIFIED) {
            throw new RuntimeException(
                "composer.json does not match the peeled version of {$backupPath}. "
                . 'Run the rollback and peel commands again before releasing.',
            );
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function decodeManifest(string $path, string $label): array
    {
        $manifest = json_decode((string) @file_get_contents($path), associative: true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest)) {
            throw new RuntimeException("{$label} does not contain valid JSON.");
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
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
