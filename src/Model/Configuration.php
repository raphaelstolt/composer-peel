<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

final class Configuration
{
    /** @var array<int, string> */
    private array $peelSections = [
        'require-dev',
        'autoload-dev',
        'scripts',
        'scripts-descriptions',
        'scripts-aliases',
    ];

    private const PROTECTED_SECTIONS = [
        'name',
        'description',
        'version',
        'type',
        'keywords',
        'homepage',
        'readme',
        'time',
        'license',
        'authors',
        'support',
        'funding',
        'require',
        'conflict',
        'replace',
        'provide',
        'suggest',
        'autoload',
        'minimum-stability',
        'prefer-stable',
        'bin',
        'archive',
        'abandoned',
        'non-feature-branches',
        'config',
        'extra',
    ];

    private bool $backupEnabled = true;
    private string $backupPath = '.composer-unpeeled.json';
    private string $beforeTagCommitMessage = 'chore(dist): prepare Composer manifest for release';
    private string $afterTagCommitMessage = 'chore: restore development Composer manifest';

    /** @var array<int, string> */
    private array $managedFiles = [
        'CHANGELOG.md',
        'bin/',
    ];

    /**
     * @return array<int, string>
     */
    public function getPeelSections(): array
    {
        return $this->peelSections;
    }

    /**
     * @param array<int, string> $peelSections
     */
    public function setPeelSections(array $peelSections): void
    {
        foreach ($peelSections as $section) {
            if (in_array($section, self::PROTECTED_SECTIONS, true)) {
                throw new \RuntimeException(sprintf(
                    'The configured section "%s" is protected and cannot be peeled.',
                    $section,
                ));
            }
        }
        $this->peelSections = $peelSections;
    }

    public function isBackupEnabled(): bool
    {
        return $this->backupEnabled;
    }

    public function setBackupEnabled(bool $backupEnabled): void
    {
        $this->backupEnabled = $backupEnabled;
    }

    public function getBackupPath(): string
    {
        return $this->backupPath;
    }

    public function setBackupPath(string $backupPath): void
    {
        $this->backupPath = $backupPath;
    }

    public function getBeforeTagCommitMessage(): string
    {
        return $this->beforeTagCommitMessage;
    }

    public function setBeforeTagCommitMessage(string $beforeTagCommitMessage): void
    {
        $this->beforeTagCommitMessage = $beforeTagCommitMessage;
    }

    public function getAfterTagCommitMessage(): string
    {
        return $this->afterTagCommitMessage;
    }

    public function setAfterTagCommitMessage(string $afterTagCommitMessage): void
    {
        $this->afterTagCommitMessage = $afterTagCommitMessage;
    }

    /**
     * @return array<int, string>
     */
    public function getManagedFiles(): array
    {
        return $this->managedFiles;
    }

    /**
     * @param array<int, string> $managedFiles
     */
    public function setManagedFiles(array $managedFiles): void
    {
        $this->managedFiles = $managedFiles;
    }
}
