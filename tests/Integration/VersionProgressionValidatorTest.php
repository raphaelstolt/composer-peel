<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\VersionProgressionValidator;

class VersionProgressionValidatorTest extends TestCase
{
    private string $testDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Could not get current working directory.');
        }
        $this->originalDir = $cwd;
        $this->testDir = sys_get_temp_dir() . '/composer-peel-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email "test@example.com"');
        file_put_contents('test.txt', 'test');
        exec('git add test.txt');
        exec('git commit -m "Initial commit"');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testValidatePassesWhenNoTagsExist(): void
    {
        $validator = new VersionProgressionValidator();
        $validator->validate('v1.0.0');
        $this->assertTrue(true); // Should not throw exception
    }

    public function testValidateThrowsExceptionWhenTagAlreadyExists(): void
    {
        exec('git tag v1.0.0');

        $validator = new VersionProgressionValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("The provided Git tag 'v1.0.0' already exists.");

        $validator->validate('v1.0.0');
    }

    public function testValidateThrowsExceptionWhenTagIsLessThanLatestReleasedVersion(): void
    {
        exec('git tag v1.1.0');

        $validator = new VersionProgressionValidator();

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            "The requested version 'v1.0.0' must be greater than the latest released version 'v1.1.0'.",
        );

        $validator->validate('v1.0.0');
    }

    public function testValidatePassesWhenTagIsGreaterThanLatestReleasedVersion(): void
    {
        exec('git tag v1.0.0');

        $validator = new VersionProgressionValidator();
        $validator->validate('v1.1.0');
        $this->assertTrue(true); // Should not throw exception
    }
}
