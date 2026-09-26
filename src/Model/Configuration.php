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
    private bool $backupEnabled = true;
    private string $backupPath = '.composer-unpeeled.json';
    private string $beforeTagCommitMessage = 'chore(dist): prepare Composer manifest for release';
    private string $afterTagCommitMessage = 'chore: restore development Composer manifest';

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
}
