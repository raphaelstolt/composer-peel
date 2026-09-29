<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

final class ValidationResult
{
    /** @var array<int, ValidationCheck> */
    private array $checks = [];

    public function add(ValidationCheck $check): void
    {
        $this->checks[] = $check;
    }

    /**
     * @return array<int, ValidationCheck>
     */
    public function getChecks(): array
    {
        return $this->checks;
    }

    public function isValid(): bool
    {
        foreach ($this->checks as $check) {
            if ($check->hasFailed()) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<int, string>
     */
    public function getViolations(): array
    {
        $violations = [];
        foreach ($this->checks as $check) {
            if (!$check->hasFailed()) {
                continue;
            }

            $violations = [...$violations, ...$check->getMessages()];
        }

        return $violations;
    }
}
