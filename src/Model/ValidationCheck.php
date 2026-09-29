<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

final class ValidationCheck
{
    public const PASSED = 'passed';
    public const FAILED = 'failed';
    public const SKIPPED = 'skipped';

    /**
     * @param array<int, string> $messages
     */
    private function __construct(
        private string $description,
        private string $status,
        private array $messages = [],
    ) {}

    public static function passed(string $description): self
    {
        return new self($description, self::PASSED);
    }

    /**
     * @param array<int, string> $messages
     */
    public static function failed(string $description, array $messages): self
    {
        return new self($description, self::FAILED, $messages);
    }

    public static function skipped(string $description, string $reason): self
    {
        return new self($description, self::SKIPPED, [$reason]);
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function hasFailed(): bool
    {
        return $this->status === self::FAILED;
    }

    /**
     * @return array<int, string>
     */
    public function getMessages(): array
    {
        return $this->messages;
    }
}
