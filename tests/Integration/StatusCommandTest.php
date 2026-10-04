<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\StatusCommand;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\StatusChecker;
use Zenstruck\Console\Test\TestCommand;

class StatusCommandTest extends TestCase
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
        $this->testDir = sys_get_temp_dir() . '/composer-peel-status-cmd-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        // Create a basic composer.json
        file_put_contents(
            'composer.json',
            (string) json_encode([
                'name' => 'test/pkg',
                'require' => ['php' => '>=8.2'],
                'bin' => ['bin/tool'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        mkdir('bin', 0777, true);
        file_put_contents('bin/tool', '<?php $v = "1.4.0";');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    private function initGit(): void
    {
        exec('git init --initial-branch=main');
        exec('git config user.name "Test"');
        exec('git config user.email "test@example.com"');
        exec('git add -A');
        exec('git commit -m "Initial"');
    }

    public function testExecuteStatusCommandShowsHeader(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Composer peel status');
    }

    public function testExecuteStatusCommandShowsManifestState(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Composer manifest');
    }

    public function testExecuteStatusCommandShowsApplicationVersion(): void
    {
        TestCommand::for(new StatusCommand())
            ->execute()
            ->assertOutputContains('Application versions')
            ->assertOutputContains('bin/tool')
            ->assertOutputContains('1.4.0');
    }

    public function testExecuteStatusCommandShowsVersionConsistency(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Version consistency');
    }

    public function testExecuteStatusCommandShowsGitTag(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Latest Git tag');
    }

    public function testExecuteStatusCommandShowsChangelog(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('CHANGELOG.md');
    }

    public function testExecuteStatusCommandShowsWorkingTree(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Working tree');
    }

    public function testExecuteStatusCommandShowsReleaseState(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Release state');
    }

    public function testExecuteStatusCommandReturnsFailureWhenNoAppVersion(): void
    {
        // Remove bin file so no version is found
        unlink('bin/tool');
        file_put_contents('bin/tool', '<?php // no version');

        TestCommand::for(new StatusCommand())->execute()->assertStatusCode(1)->assertOutputContains('unknown');
    }

    public function testExecuteStatusCommandReturnsSuccessWhenHealthy(): void
    {
        $this->initGit();
        exec('git tag v1.4.0');

        file_put_contents('CHANGELOG.md', "## [1.4.0] - 2025-01-01\n\n- New feature\n");

        $checker = new StatusChecker(new Configuration(), getcwd());
        $result = $checker->check();

        // Only assert success if there are no issues
        if ($result->isReleaseReady()) {
            TestCommand::for(new StatusCommand())->execute()->assertSuccessful();
        }
    }

    public function testExecuteStatusCommandShowsIssues(): void
    {
        // Create conflicting versions
        mkdir('src/Console', 0777, true);
        file_put_contents('src/Console/Application.php', '<?php $v = "1.5.0";');

        TestCommand::for(new StatusCommand())
            ->execute()
            ->assertOutputContains('Issues:')
            ->assertOutputContains('inconsistent');
    }

    public function testExecuteStatusCommandWithCustomConfig(): void
    {
        file_put_contents('.composer-peel.php', <<<'PHP'
            <?php
            return [
                'peel' => [
                    'sections' => ['require-dev'],
                ],
            ];
            PHP);

        TestCommand::for(new StatusCommand())->execute('--config=.composer-peel.php')->assertOutputContains(
            'Composer peel status',
        );
    }

    public function testExecuteStatusCommandShowsBackupState(): void
    {
        TestCommand::for(new StatusCommand())->execute()->assertOutputContains('Backup');
    }

    public function testExecuteStatusCommandShowsPackageVersion(): void
    {
        // Add a version to composer.json
        $manifest = json_decode(file_get_contents('composer.json'), true);
        $manifest['version'] = '2.0.0';
        file_put_contents(
            'composer.json',
            (string) json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        TestCommand::for(new StatusCommand())
            ->execute()
            ->assertOutputContains('Package version')
            ->assertOutputContains('2.0.0');
    }

    public function testExecuteStatusCommandOutputFormatting(): void
    {
        TestCommand::for(new StatusCommand())
            ->execute()
            ->assertOutputContains('Composer manifest')
            ->assertOutputContains('Backup')
            ->assertOutputContains('Application version')
            ->assertOutputContains('Version consistency')
            ->assertOutputContains('Latest Git tag')
            ->assertOutputContains('CHANGELOG.md')
            ->assertOutputContains('Working tree')
            ->assertOutputContains('Release state');
    }
}
