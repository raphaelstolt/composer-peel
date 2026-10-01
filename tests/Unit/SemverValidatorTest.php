<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Stolt\ComposerPeel\Model\SemverValidator;

class SemverValidatorTest extends TestCase
{
    #[DataProvider('provideValidTags')]
    public function testIsValidReturnsTrueForValidTags(string $tag): void
    {
        $validator = new SemverValidator();
        static::assertTrue($validator->isValid($tag));
    }

    #[DataProvider('provideInvalidTags')]
    public function testIsValidReturnsFalseForInvalidTags(string $tag): void
    {
        $validator = new SemverValidator();
        static::assertFalse($validator->isValid($tag));
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function provideValidTags(): array
    {
        return [
            'standard' => ['1.0.0'],
            'with v prefix' => ['v1.0.0'],
            'minor update' => ['v1.1.0'],
            'patch update' => ['v1.0.1'],
            'pre-release' => ['v1.0.0-RC1'],
        ];
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function provideInvalidTags(): array
    {
        return [
            'missing minor/patch' => ['v1'],
            'missing patch' => ['v1.0'],
            'letters only' => ['vOne'],
            'random string' => ['invalid-tag'],
        ];
    }
}
