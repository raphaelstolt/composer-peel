<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class VersionProgressionValidator
{
    private SemverValidator $semverValidator;

    public function __construct(?SemverValidator $semverValidator = null)
    {
        $this->semverValidator = $semverValidator ?? new SemverValidator();
    }

    public function validate(string $requestedTag): void
    {
        exec('git tag', $output, $resultCode);
        if ($resultCode !== 0) {
            throw new RuntimeException('Failed to retrieve Git tags.');
        }

        if (in_array($requestedTag, $output, true)) {
            throw new RuntimeException("The provided Git tag '{$requestedTag}' already exists.");
        }

        $semverTags = array_filter($output, function (string $tag): bool {
            return $this->semverValidator->isValid($tag);
        });

        if (count($semverTags) === 0) {
            return;
        }

        $latestTag = '';
        foreach ($semverTags as $tag) {
            if ($latestTag === '') {
                $latestTag = $tag;
                continue;
            }
            if (version_compare(ltrim($tag, 'v'), ltrim($latestTag, 'v'), '>')) {
                $latestTag = $tag;
            }
        }

        if (version_compare(ltrim($requestedTag, 'v'), ltrim($latestTag, 'v'), '<=')) {
            throw new RuntimeException(
                "The requested version '{$requestedTag}' must be greater than the latest released version '{$latestTag}'.",
            );
        }
    }
}
