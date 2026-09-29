<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\ReleaseCommand;
use Stolt\ComposerPeel\Model\ComposerPeeler;
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

    public function testExecuteReleaseFailsIfManifestHasNotBeenPeeled(): void
    {
        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains(
                'Backup file .composer-unpeeled.json does not exist. Run the peel command before releasing.',
            );

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
    }

    public function testExecuteReleaseFailsIfPeeledManifestIsInvalid(): void
    {
        (new ComposerPeeler())->peel();

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertIsArray($manifest);
        $manifest['require-dev'] = ['phpunit/phpunit' => '^10.0'];
        file_put_contents('composer.json', (string) json_encode($manifest));

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertStatusCode(1)
            ->assertOutputContains('The peeled composer.json is invalid:')
            ->assertOutputContains("Section 'require-dev' has not been peeled.");

        exec('git log --oneline', $logOutput);
        static::assertCount(1, $logOutput);

        exec('git tag', $tagOutput);
        static::assertSame([], $tagOutput);
    }

    public function testExecuteRelease(): void
    {
        (new ComposerPeeler())->peel();

        TestCommand::for(new ReleaseCommand())
            ->execute('v1.0.0')
            ->assertSuccessful()
            ->assertOutputContains('Release workflow completed successfully for tag: v1.0.0.');

        exec('git log --oneline', $logOutput);
        static::assertStringContainsString('chore(dist): prepare Composer manifest for release', implode("\n", $logOutput));
        static::assertStringNotContainsString('chore: restore development Composer manifest', implode("\n", $logOutput));

        exec('git tag', $tagOutput);
        static::assertContains('v1.0.0', $tagOutput);

        exec('git show v1.0.0:composer.json', $taggedManifestOutput);
        $taggedManifest = json_decode(implode("\n", $taggedManifestOutput), associative: true);
        static::assertIsArray($taggedManifest);
        static::assertArrayNotHasKey('require-dev', $taggedManifest);

        static::assertFileExists('.composer-unpeeled.json');
    }
}
