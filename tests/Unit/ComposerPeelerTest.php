<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\ComposerPeeler;

class ComposerPeelerTest extends TestCase
{
    private ComposerPeeler $peeler;
    private string $testDir;
    private string $originalDir;

    protected function setUp(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Could not get current working directory.');
        }
        $this->originalDir = $cwd;
        $this->testDir = sys_get_temp_dir() . '/composer-peel-unit-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        $this->peeler = new ComposerPeeler();
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        if (file_exists($this->testDir . '/composer.json')) {
            unlink($this->testDir . '/composer.json');
        }
        if (file_exists($this->testDir . '/.composer-unpeeled.json')) {
            unlink($this->testDir . '/.composer-unpeeled.json');
        }
        rmdir($this->testDir);
    }

    public function testPeelThrowsExceptionWhenManifestNotFound(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer.json not found in current directory.');
        $this->peeler->peel();
    }

    public function testPeelRemovesConfiguredSections(): void
    {
        $initialManifest = [
            'name' => 'vendor/package',
            'require' => ['php' => '^8.2'],
            'require-dev' => ['phpunit/phpunit' => '^10.0'],
            'scripts' => ['test' => 'phpunit'],
            'scripts-descriptions' => ['test' => 'Run tests'],
        ];
        file_put_contents('composer.json', json_encode($initialManifest));

        $this->peeler->peel();

        $peeledManifest = json_decode((string) file_get_contents('composer.json'), associative: true);

        static::assertArrayHasKey('name', $peeledManifest);
        static::assertArrayHasKey('require', $peeledManifest);
        static::assertArrayNotHasKey('require-dev', $peeledManifest);
        static::assertArrayNotHasKey('scripts', $peeledManifest);
        static::assertArrayNotHasKey('scripts-descriptions', $peeledManifest);
    }

    public function testPeelDryRunDoesNotModifyFiles(): void
    {
        $initialManifest = [
            'name' => 'vendor/package',
            'require-dev' => ['phpunit/phpunit' => '^10.0'],
        ];
        file_put_contents('composer.json', json_encode($initialManifest));

        $result = $this->peeler->simulatePeel();

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertContains('require-dev', $result->getRemovedSections());
        static::assertGreaterThan(0, $result->getOriginalSize());
        static::assertGreaterThan(0, $result->getProjectedSize());

        static::assertArrayHasKey('require-dev', $manifest);
        static::assertFileDoesNotExist('.composer-unpeeled.json');
    }
}
