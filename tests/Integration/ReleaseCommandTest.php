<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\PeelCommand;
use Stolt\ComposerPeel\Command\ReleaseCommand;
use Stolt\ComposerPeel\Command\RollbackCommand;
use Stolt\ComposerPeel\Model\Configuration;
use Zenstruck\Console\Test\TestCommand;

class ReleaseCommandTest extends TestCase
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

    public function testExecuteReleaseThrowsErrorForInvalidSemver(): void
    {
        TestCommand::for(new ReleaseCommand())
            ->execute('invalid-tag')
            ->assertStatusCode(1)
            ->assertOutputContains("The provided Git tag 'invalid-tag' is not a valid semantic version.");
    }

    public function testExecuteReleaseThrowsErrorForDirtyWorkingTree(): void
    {
        file_put_contents('README.md', 'Uncommitted change');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('The composer-peel release workflow requires a clean working tree.');
    }

    public function testExecuteReleaseFailsIfThereIsNothingToCommit(): void
    {
        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains("Git command failed: 'git' 'commit'");

        exec('git log --oneline', $logOutput);
        static::assertCount(1, $logOutput);

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
    }

    public function testExecuteReleaseFailsIfPeelBackupIsNotTheConfiguredBackup(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('--backup-file=custom-backup.json')
            ->assertSuccessful();

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('The composer-peel release workflow requires a clean working tree.');

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
    }

    public function testExecuteRelease(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('Metadata peeled successfully.');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertSuccessful()
            ->assertOutputContains('Release workflow completed successfully for tag: v1.0.0.');

        exec('git log --pretty=%s', $logOutput);
        static::assertSame(
            ['chore(dist): prepare Composer manifest for release', 'Initial commit'],
            $logOutput,
        );

        exec('git tag --points-at HEAD', $tagOutput);
        static::assertSame(['v1.0.0'], $tagOutput);

        $taggedManifest = $this->readManifestAt('v1.0.0');
        foreach ((new Configuration())->getPeelSections() as $section) {
            static::assertArrayNotHasKey($section, $taggedManifest);
        }
        static::assertArrayHasKey('require', $taggedManifest);
        static::assertArrayHasKey('autoload', $taggedManifest);

        exec('git status --porcelain --untracked-files=all', $statusOutput);
        static::assertSame(['?? .composer-unpeeled.json'], $statusOutput);
    }

    public function testExecuteReleaseWithCustomBackupFile(): void
    {
        TestCommand::for(new PeelCommand())
            ->execute('--backup-file=custom-backup.json')
            ->assertSuccessful();

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0 --backup-file=custom-backup.json')
            ->assertSuccessful();

        exec('git tag --points-at HEAD', $tagOutput);
        static::assertSame(['v1.0.0'], $tagOutput);

        static::assertFileExists('custom-backup.json');
    }

    public function testExecuteCompleteReleaseWorkflow(): void
    {
        $developmentManifest = (string) file_get_contents('composer.json');

        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();
        TestCommand::for(new ReleaseCommand())->execute('v1.0.0')->assertSuccessful();
        TestCommand::for(new RollbackCommand())->execute('--commit')->assertSuccessful();

        exec('git log --pretty=%s', $logOutput);
        static::assertSame(
            [
                'chore: restore development Composer manifest',
                'chore(dist): prepare Composer manifest for release',
                'Initial commit',
            ],
            $logOutput,
        );

        exec('git tag --points-at HEAD~1', $tagOutput);
        static::assertSame(['v1.0.0'], $tagOutput);

        static::assertArrayNotHasKey('require-dev', $this->readManifestAt('v1.0.0'));
        static::assertArrayHasKey('require-dev', $this->readManifestAt('HEAD'));
        static::assertSame($developmentManifest, file_get_contents('composer.json'));

        exec('git status --porcelain --untracked-files=all', $statusOutput);
        static::assertSame([], $statusOutput);
    }

    /**
     * @return array<string, mixed>
     */
    private function readManifestAt(string $revision): array
    {
        exec('git show ' . escapeshellarg($revision . ':composer.json'), $manifestOutput);
        $manifest = json_decode(implode("\n", $manifestOutput), associative: true);
        static::assertIsArray($manifest);

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
