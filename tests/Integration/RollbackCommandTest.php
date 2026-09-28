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
            ->assertOutputContains('✓ composer.json restored')
            ->assertOutputContains('✓ Backup removed');

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
            ->assertOutputContains('✓ composer.json restored')
            ->assertOutputContains('✓ Backup kept');

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
}
