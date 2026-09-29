<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\ConfigurationLoader;

class ConfigurationLoaderTest extends TestCase
{
    private string $testDir;

    protected function setUp(): void
    {
        $this->testDir = sys_get_temp_dir() . '/composer-peel-config-test-' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDir . '/.composer-peel.php')) {
            unlink($this->testDir . '/.composer-peel.php');
        }
        rmdir($this->testDir);
    }

    public function testLoadConfig(): void
    {
        copy(from: getcwd() . '/tests/fixtures/.composer-peel.php', to: $this->testDir . '/.composer-peel.php');

        $loader = new ConfigurationLoader();
        $config = $loader->load($this->testDir . '/.composer-peel.php');

        static::assertSame(['require-dev', 'scripts'], $config->getPeelSections());
        static::assertFalse($config->isBackupEnabled());
        static::assertSame('my-backup.json', $config->getBackupPath());
        static::assertSame('My before message', $config->getBeforeTagCommitMessage());
        static::assertSame('My after message', $config->getAfterTagCommitMessage());
    }

    public function testLoadConfigWithMissingFileThrowsException(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Configuration file not found: non-existent.php');

        $loader = new ConfigurationLoader();
        $loader->load('non-existent.php');
    }

    public function testLoadConfigWithProtectedSectionThrowsException(): void
    {
        $configContent = <<<PHP
<?php
return [
    'peel' => [
        'sections' => [
            'require',
            'autoload-dev',
        ],
    ],
];
PHP;
        file_put_contents($this->testDir . '/.composer-peel.php', $configContent);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The configured section "require" is protected and cannot be peeled.');

        $loader = new ConfigurationLoader();
        $loader->load($this->testDir . '/.composer-peel.php');
    }
}
