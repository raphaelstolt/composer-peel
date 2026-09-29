<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\RollbackCommand;
use Zenstruck\Console\Test\TestCommand;

class RollbackCommandTest extends TestCase
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
        $this->testDir = sys_get_temp_dir() . '/composer-peel-rollback-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testExecuteRollbackFailsIfBackupDoesNotExist(): void
    {
        TestCommand::for(new RollbackCommand())
            ->execute()
            ->assertStatusCode(1)
            ->assertOutputContains('Backup file .composer-unpeeled.json does not exist.');
    }

    public function testExecuteRollbackFailsIfBackupIsInvalidJson(): void
    {
        file_put_contents('.composer-unpeeled.json', data: 'invalid json');

        TestCommand::for(new RollbackCommand())
            ->execute()
            ->assertStatusCode(1)
            ->assertOutputContains('Backup file .composer-unpeeled.json does not contain valid JSON.');
    }

    public function testExecuteRollbackSuccessfullyRestoresAndRemovesBackup(): void
    {
        $backupManifest = ['name' => 'test/package', 'require-dev' => ['phpunit/phpunit' => '^10.0']];
        $peeledManifest = ['name' => 'test/package'];

        file_put_contents('.composer-unpeeled.json', (string) json_encode($backupManifest));
        file_put_contents('composer.json', (string) json_encode($peeledManifest));

        TestCommand::for(new RollbackCommand())
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('Restoring composer.json from .composer-unpeeled.json')
            ->assertOutputContains('composer.json restored successfully.')
            ->assertOutputContains('Backup removed successfully.');

        $currentManifestContent = file_get_contents('composer.json');
        static::assertIsString($currentManifestContent);

        $currentManifest = json_decode($currentManifestContent, associative: true);
        static::assertIsArray($currentManifest);
        static::assertArrayHasKey('require-dev', $currentManifest);

        static::assertFileDoesNotExist('.composer-unpeeled.json');
    }

    public function testExecuteRollbackKeepsBackupWhenOptionProvided(): void
    {
        $backupManifest = ['name' => 'test/package'];

        file_put_contents('.composer-unpeeled.json', (string) json_encode($backupManifest));
        file_put_contents('composer.json', (string) json_encode($backupManifest));

        TestCommand::for(new RollbackCommand())
            ->execute('--keep-backup')
            ->assertSuccessful()
            ->assertOutputContains('Restoring composer.json from .composer-unpeeled.json')
            ->assertOutputContains('composer.json restored successfully.')
            ->assertOutputContains('Backup kept.');

        static::assertFileExists('.composer-unpeeled.json');
    }

    public function testExecuteRollbackWithCustomBackupFile(): void
    {
        $backupManifest = ['name' => 'test/package'];

        file_put_contents('custom-backup.json', (string) json_encode($backupManifest));
        file_put_contents('composer.json', (string) json_encode($backupManifest));

        TestCommand::for(new RollbackCommand())->execute('--backup-file=custom-backup.json')->assertSuccessful();

        static::assertFileDoesNotExist('custom-backup.json');
    }

    public function testExecuteRollbackCommitsRestoredManifestWhenOptionProvided(): void
    {
        $this->initGitRepositoryWithPeeledManifest();

        TestCommand::for(new RollbackCommand())
            ->execute('--commit')
            ->assertSuccessful()
            ->assertOutputContains('composer.json restored successfully.')
            ->assertOutputContains('Restored composer.json committed successfully.')
            ->assertOutputContains('Backup removed successfully.');

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore: restore development Composer manifest'], $logOutput);

        exec('git show HEAD:composer.json', $committedManifestOutput);
        $committedManifest = json_decode(implode("\n", $committedManifestOutput), associative: true);
        static::assertIsArray($committedManifest);
        static::assertArrayHasKey('require-dev', $committedManifest);

        exec('git status --porcelain', $statusOutput);
        static::assertSame([], $statusOutput);

        static::assertFileDoesNotExist('.composer-unpeeled.json');
    }

    public function testExecuteRollbackCommitsRestoredManifestAndKeepsBackup(): void
    {
        $this->initGitRepositoryWithPeeledManifest();

        TestCommand::for(new RollbackCommand())
            ->execute('--commit --keep-backup')
            ->assertSuccessful()
            ->assertOutputContains('Restored composer.json committed successfully.')
            ->assertOutputContains('Backup kept.');

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore: restore development Composer manifest'], $logOutput);

        static::assertFileExists('.composer-unpeeled.json');
    }

    public function testExecuteRollbackUsesConfiguredCommitMessage(): void
    {
        $this->initGitRepositoryWithPeeledManifest();

        file_put_contents('custom-config.php', <<<'PHP'
            <?php

            return [
                'git' => [
                    'commit_messages' => [
                        'after_tag' => 'chore: back to development',
                    ],
                ],
            ];
            PHP);

        TestCommand::for(new RollbackCommand())
            ->execute('--commit --config=custom-config.php')
            ->assertSuccessful();

        exec('git log -1 --pretty=%s', $logOutput);
        static::assertSame(['chore: back to development'], $logOutput);
    }

    public function testExecuteRollbackFailsToCommitOutsideOfGitRepository(): void
    {
        $backupManifest = ['name' => 'test/package', 'require-dev' => ['phpunit/phpunit' => '^10.0']];

        file_put_contents('.composer-unpeeled.json', (string) json_encode($backupManifest));
        file_put_contents('composer.json', (string) json_encode(['name' => 'test/package']));

        TestCommand::for(new RollbackCommand())
            ->execute('--commit')
            ->assertStatusCode(1)
            ->assertOutputContains('composer.json restored successfully.')
            ->assertOutputContains('Git command failed');

        static::assertFileExists('.composer-unpeeled.json');
    }

    private function initGitRepositoryWithPeeledManifest(): void
    {
        $backupManifest = ['name' => 'test/package', 'require-dev' => ['phpunit/phpunit' => '^10.0']];
        $peeledManifest = ['name' => 'test/package'];

        file_put_contents('composer.json', (string) json_encode($peeledManifest));

        exec('git init');
        exec('git config user.name "Test User"');
        exec('git config user.email "test@example.com"');
        exec('git add composer.json');
        exec('git commit -m "chore(dist): prepare Composer manifest for release"');

        file_put_contents('.composer-unpeeled.json', (string) json_encode($backupManifest));
    }
}
