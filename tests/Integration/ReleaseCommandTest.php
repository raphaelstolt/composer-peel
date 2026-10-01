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

    public function testExecuteReleaseThrowsErrorForExistingTag(): void
    {
        exec('git tag v1.0.0');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains("The provided Git tag 'v1.0.0' already exists.");
    }

    public function testExecuteReleaseThrowsErrorForTagNotGreaterThanLatest(): void
    {
        exec('git tag v1.1.0');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains(
                "The requested version 'v1.0.0' must be greater than the latest released version 'v1.1.0'.",
            );
    }

    public function testExecuteReleaseThrowsErrorForDirtyWorkingTree(): void
    {
        file_put_contents('README.md', 'Uncommitted change');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('The composer-peel release workflow requires a clean working tree.');
    }

    public function testExecuteReleaseFailsIfManifestHasNotBeenPeeled(): void
    {
        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains(
                'Backup file .composer-unpeeled.json does not exist. Run the peel command before releasing.',
            );

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseFailsForUnpeeledManifestWithUnrelatedChange(): void
    {
        $manifest = $this->readManifestAt('HEAD');
        $manifest['license'] = 'BSD-3-Clause';
        file_put_contents('composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('Run the peel command before releasing.');

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseFailsIfManifestMatchesBackup(): void
    {
        copy('composer.json', '.composer-unpeeled.json');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('composer.json has not been peeled. Run the peel command before releasing.');

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseFailsIfManifestWasModifiedAfterPeeling(): void
    {
        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertIsArray($manifest);
        $manifest['license'] = 'BSD-3-Clause';
        file_put_contents('composer.json', (string) json_encode($manifest, JSON_PRETTY_PRINT));

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('composer.json does not match the peeled version of .composer-unpeeled.json.')
            ->assertOutputContains('Run the rollback and peel commands again before releasing.');

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseFailsIfBackupIsInvalidJson(): void
    {
        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();
        file_put_contents('.composer-unpeeled.json', 'invalid json');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('Backup file .composer-unpeeled.json does not contain valid JSON.');

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseFailsIfPeelBackupIsNotTheConfiguredBackup(): void
    {
        TestCommand::for(new PeelCommand())->execute('--backup-file=custom-backup.json')->assertSuccessful();

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
        static::assertSame(['chore: release version v1.0.0', 'Initial commit'], $logOutput);

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

    public function testExecuteReleaseWithCustomCommitMessage(): void
    {
        file_put_contents('custom-config.php', <<<'PHP'
            <?php

            return [
                'git' => [
                    'commit_messages' => [
                        'release' => 'chore: configured release message',
                    ],
                ],
            ];
            PHP);
        exec('git add custom-config.php && git commit -m "Add configuration"');

        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        TestCommand::for(new ReleaseCommand())->execute(
            'v1.0.0 --config=custom-config.php --commit-message="chore: release v1.0.0"',
        )->assertSuccessful();

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore: release v1.0.0'], $logOutput);

        exec('git tag --points-at HEAD', $tagOutput);
        static::assertSame(['v1.0.0'], $tagOutput);
    }

    public function testExecuteReleaseFailsForEmptyCommitMessage(): void
    {
        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0 --commit-message=" "')
            ->assertStatusCode(1)
            ->assertOutputContains('The --commit-message option requires a non-empty message.');

        $this->assertNoReleaseCreated();
    }

    public function testExecuteReleaseWithCustomBackupFile(): void
    {
        TestCommand::for(new PeelCommand())->execute('--backup-file=custom-backup.json')->assertSuccessful();

        TestCommand::for(new ReleaseCommand())->execute('v1.0.0 --backup-file=custom-backup.json')->assertSuccessful();

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
                'chore: release version v1.0.0',
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

    public function testExecuteReleaseAllowsChangelogAndBinFiles(): void
    {
        mkdir('bin');
        file_put_contents('bin/composer-peel', 'binary content');
        exec('git add bin/composer-peel');
        exec('git commit -m "Add binary"');

        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        file_put_contents('CHANGELOG.md', 'changelog content');
        file_put_contents('bin/composer-peel', 'modified binary content');
        file_put_contents('bin/server', 'new binary content');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertSuccessful()
            ->assertOutputContains('Release workflow completed successfully for tag: v1.0.0.');

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore: release version v1.0.0'], $logOutput);

        exec('git tag --points-at HEAD', $tagOutput);
        static::assertSame(['v1.0.0'], $tagOutput);

        exec('git show --name-only ' . escapeshellarg('v1.0.0'), $showOutput);
        static::assertContains('CHANGELOG.md', $showOutput);
        static::assertContains('bin/composer-peel', $showOutput);
        static::assertContains('bin/server', $showOutput);
        static::assertContains('composer.json', $showOutput);
    }

    public function testExecuteReleaseAllowsCustomManagedFiles(): void
    {
        file_put_contents('custom-config.php', <<<'PHP'
            <?php

            return [
                'release' => [
                    'managed_files' => [
                        'README.md',
                        'scripts/',
                    ],
                ],
            ];
            PHP);
        exec('git add custom-config.php && git commit -m "Add custom managed_files configuration"');

        mkdir('scripts');
        file_put_contents('scripts/build.sh', 'build content');
        exec('git add scripts/build.sh');
        exec('git commit -m "Add script"');
        
        file_put_contents('README.md', 'some readme content');
        exec('git add README.md');
        exec('git commit -m "Add README"');

        TestCommand::for(new PeelCommand())->execute('--config=custom-config.php')->assertSuccessful();

        file_put_contents('README.md', 'modified readme content');
        file_put_contents('scripts/build.sh', 'modified build content');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0 --config=custom-config.php')
            ->assertSuccessful()
            ->assertOutputContains('Release workflow completed successfully for tag: v1.0.0.');

        exec('git show --name-only ' . escapeshellarg('v1.0.0'), $showOutput);
        static::assertContains('README.md', $showOutput);
        static::assertContains('scripts/build.sh', $showOutput);
        static::assertContains('composer.json', $showOutput);
    }

    public function testExecuteReleaseDryRun(): void
    {
        mkdir('bin');
        file_put_contents('bin/composer-peel', 'binary content');
        exec('git add bin/composer-peel');
        exec('git commit -m "Add binary"');

        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        file_put_contents('CHANGELOG.md', 'changelog content');
        file_put_contents('bin/composer-peel', 'modified binary content');

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0 --dry-run')
            ->assertSuccessful()
            ->assertOutputContains('Files to be committed:')
            ->assertOutputContains('+ composer.json')
            ->assertOutputContains('+ CHANGELOG.md')
            ->assertOutputContains('+ bin/composer-peel')
            ->assertOutputContains('Commit: chore: release version v1.0.0')
            ->assertOutputContains('Tag: v1.0.0');

        exec('git log --oneline', $logOutput);
        static::assertCount(2, $logOutput);

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
    }

    public function testExecuteReleaseReplacesVersionPlaceholderInCommitMessage(): void
    {
        file_put_contents('custom-config.php', <<<'PHP'
            <?php

            return [
                'git' => [
                    'commit_messages' => [
                        'release' => 'chore(release): release {{version}}',
                    ],
                ],
            ];
            PHP);
        exec('git add custom-config.php && git commit -m "Add configuration"');

        TestCommand::for(new PeelCommand())->execute()->assertSuccessful();

        TestCommand::for(new ReleaseCommand())->execute(
            'v1.0.0 --config=custom-config.php',
        )->assertSuccessful();

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore(release): release v1.0.0'], $logOutput);
    }

    private function assertNoReleaseCreated(): void
    {
        exec('git log --oneline', $logOutput);
        static::assertCount(1, $logOutput);

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
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
