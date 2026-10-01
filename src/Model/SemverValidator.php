<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

class SemverValidator
{
    public function isValid(string $tag): bool
    {
        $regex = '/^v?(0|[1-9]\d*)\.(0|[1-9]\d*)\.(0|[1-9]\d*)(?:-((?:0|[1-9]\d*|\d*[a-zA-Z-][0-zA-Z0-9-]*)(?:\.(?:0|[1-9]\d*|\d*[a-zA-Z-][0-zA-Z0-9-]*))*))?(?:\+([0-9a-zA-Z-]+(?:\.[0-9a-zA-Z-]+)*))?$/';
        return preg_match($regex, $tag) === 1;
    }
}
