<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\InitCommand;
use Zenstruck\Console\Test\TestCommand;

class InitCommandTest extends TestCase
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
        $this->testDir = sys_get_temp_dir() . '/composer-peel-init-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testExecuteCreatesConfigurationFile(): void
    {
        TestCommand::for(new InitCommand())
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('Default configuration written to .composer-peel.php successfully.');

        static::assertFileExists('.composer-peel.php');

        /** @var mixed $configArray */
        $configArray = require '.composer-peel.php';

        static::assertIsArray($configArray);
        static::assertArrayHasKey('peel', $configArray);
        static::assertArrayHasKey('sections', $configArray['peel']);
        static::assertContains('require-dev', $configArray['peel']['sections']);
        static::assertArrayHasKey('release', $configArray);
        static::assertArrayHasKey('backup', $configArray['release']);
        static::assertTrue($configArray['release']['backup']['enabled']);
        static::assertSame('.composer-unpeeled.json', $configArray['release']['backup']['path']);
        static::assertArrayHasKey('files', $configArray['release']);
        static::assertContains('CHANGELOG.md', $configArray['release']['files']);
        static::assertContains('bin/', $configArray['release']['files']);
        static::assertSame('chore: release version {{version}}', $configArray['release']['commit_message']);
        static::assertArrayHasKey('rollback', $configArray);
        static::assertSame('chore: restore development Composer manifest', $configArray['rollback']['commit_message']);
    }

    public function testExecuteFailsIfConfigurationFileExists(): void
    {
        file_put_contents('.composer-peel.php', data: '<?php return [];');

        TestCommand::for(new InitCommand())
            ->execute()
            ->assertStatusCode(1)
            ->assertOutputContains(
                'Configuration file .composer-peel.php already exists. Use --overwrite to replace it.',
            );

        $content = file_get_contents('.composer-peel.php');
        static::assertSame('<?php return [];', $content);
    }

    public function testExecuteOverwritesIfOptionIsProvided(): void
    {
        file_put_contents('.composer-peel.php', data: '<?php return [];');

        TestCommand::for(new InitCommand())
            ->execute('--overwrite')
            ->assertSuccessful()
            ->assertOutputContains('Default configuration written to .composer-peel.php successfully.');

        $content = file_get_contents('.composer-peel.php');
        static::assertNotSame('<?php return [];', $content);

        /** @var mixed $configArray */
        $configArray = require '.composer-peel.php';
        static::assertIsArray($configArray);
        static::assertArrayHasKey('peel', $configArray);
    }
}
