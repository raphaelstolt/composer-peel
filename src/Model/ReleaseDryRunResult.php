<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

final class ReleaseDryRunResult
{
    /**
     * @param array<int, string> $filesToCommit
     */
    public function __construct(
        private array $filesToCommit,
        private string $commitMessage,
        private string $tag,
    ) {}

    /**
     * @return array<int, string>
     */
    public function getFilesToCommit(): array
    {
        return $this->filesToCommit;
    }

    public function getCommitMessage(): string
    {
        return $this->commitMessage;
    }

    public function getTag(): string
    {
        return $this->tag;
    }
}
