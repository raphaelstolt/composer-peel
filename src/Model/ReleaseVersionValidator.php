<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ReleaseVersionValidator
{
    private SemverValidator $semverValidator;
    private VersionProgressionValidator $progressionValidator;

    public function __construct(
        ?SemverValidator $semverValidator = null,
        ?VersionProgressionValidator $progressionValidator = null,
    ) {
        $this->semverValidator = $semverValidator ?? new SemverValidator();
        $this->progressionValidator = $progressionValidator ?? new VersionProgressionValidator();
    }

    public function validate(string $tag): void
    {
        if (!$this->semverValidator->isValid($tag)) {
            throw new RuntimeException("The provided Git tag '{$tag}' is not a valid semantic version.");
        }

        $this->progressionValidator->validate($tag);
    }
}
