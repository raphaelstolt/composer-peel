<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\PeelCommand;
use Zenstruck\Console\Test\TestCommand;

class PeelCommandTest extends TestCase
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

        copy(from: $this->originalDir . '/tests/fixtures/composer.json', to: 'composer.json');

        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email "test@example.com"');
        exec('git add composer.json');
        exec('git commit -m "Initial commit"');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testExecuteDryRun(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('--dry-run')
            ->assertSuccessful()
            ->assertOutputContains('Manifest:      composer.json')
            ->assertOutputContains('Configuration: internal defaults')
            ->assertOutputContains('Sections to be removed:')
            ->assertOutputContains('  - require-dev')
            ->assertOutputContains('  - autoload-dev')
            ->assertOutputContains('  - scripts')
            ->assertOutputContains('  - scripts-descriptions')
            ->assertOutputContains('Original size:')
            ->assertOutputContains('Projected size:')
            ->assertOutputContains('Estimated reduction:')
            ->assertOutputContains('Dry run completed. No files were modified.');

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertArrayHasKey('require-dev', $manifest);
        static::assertArrayHasKey('autoload-dev', $manifest);
        static::assertArrayHasKey('scripts', $manifest);
        static::assertArrayHasKey('scripts-descriptions', $manifest);
    }

    public function testExecuteDryRunJsonFormat(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('--dry-run --format=json')
            ->assertSuccessful()
            ->assertOutputContains('"manifest": "composer.json"')
            ->assertOutputContains('"configuration": "internal defaults"')
            ->assertOutputContains('"removed_sections": [')
            ->assertOutputContains('"require-dev"')
            ->assertOutputContains('"autoload-dev"')
            ->assertOutputContains('"scripts"')
            ->assertOutputContains('"scripts-descriptions"');

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);

        static::assertArrayHasKey('require-dev', $manifest);
        static::assertArrayHasKey('autoload-dev', $manifest);
        static::assertArrayHasKey('scripts', $manifest);
        static::assertArrayHasKey('scripts-descriptions', $manifest);
    }

    public function testExecute(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('Metadata peeled successfully.');

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertArrayNotHasKey('require-dev', $manifest);
        static::assertArrayNotHasKey('autoload-dev', $manifest);
        static::assertArrayNotHasKey('scripts', $manifest);
        static::assertArrayNotHasKey('scripts-descriptions', $manifest);

        static::assertArrayHasKey('require', $manifest);
        static::assertArrayHasKey('autoload', $manifest);

        static::assertFileExists('.composer-unpeeled.json');
    }

    public function testExecuteReleaseThrowsErrorForInvalidSemver(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('invalid-tag')
            ->assertStatusCode(1)
            ->assertOutputContains("The provided Git tag 'invalid-tag' is not a valid semantic version.");
    }

    public function testExecuteReleaseThrowsErrorForDirtyWorkingTree(): void
    {
        file_put_contents('composer.json', "\n", FILE_APPEND);

        TestCommand::for(new PeelCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('ERROR: Working tree contains uncommitted changes.')
            ->assertOutputContains('composer-peel release must start from a clean working tree.');
    }

    public function testExecuteRelease(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('v1.0.0')
            ->assertSuccessful()
            ->assertOutputContains('Release workflow completed successfully for tag: v1.0.0.');

        // Assert git tags and commits
        exec('git log --oneline', $logOutput);
        static::assertStringContainsString('chore: restore development Composer manifest', implode('
', $logOutput));
        static::assertStringContainsString('chore(dist): prepare Composer manifest for release', implode(
            '
',
            $logOutput,
        ));

        exec('git tag', $tagOutput);
        static::assertContains('v1.0.0', $tagOutput);

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertArrayHasKey('require-dev', $manifest);
    }
}
