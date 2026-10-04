<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\StatusChecker;
use Stolt\ComposerPeel\Model\StatusResult;

class StatusCheckerTest extends TestCase
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
        $this->testDir = sys_get_temp_dir() . '/composer-peel-status-test-' . uniqid();
        mkdir($this->testDir);
    }

    protected function tearDown(): void
    {
        if (file_exists($this->testDir)) {
            // Clean up git if initialized
            exec('rm -rf ' . escapeshellarg($this->testDir));
        }
    }

    private function checker(): StatusChecker
    {
        return new StatusChecker(new Configuration(), $this->testDir);
    }

    private function writeComposerJson(array $data): void
    {
        file_put_contents(
            $this->testDir . '/composer.json',
            (string) json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );
    }

    private function initGit(): void
    {
        chdir($this->testDir);
        exec('git init --initial-branch=main');
        exec('git config user.name "Test"');
        exec('git config user.email "test@example.com"');
        exec('git add -A');
        exec('git commit -m "Initial"');
        chdir($this->originalDir);
    }

    // ─── Composer manifest state ───────────────────────────────────────────

    public function testDetectsUnpeeledManifest(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'require-dev' => ['phpunit/phpunit' => '^10'],
            'scripts' => ['test' => 'phpunit'],
        ]);

        $result = $this->checker()->check();

        static::assertSame(StatusResult::MANIFEST_UNPEELED, $result->manifestState);
    }

    public function testDetectsPeeledManifestWithBackup(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        // Create backup with peel sections
        file_put_contents(
            $this->testDir . '/.composer-unpeeled.json',
            (string) json_encode([
                'name' => 'test/pkg',
                'require' => ['php' => '>=8.2'],
                'require-dev' => ['phpunit/phpunit' => '^10'],
                'scripts' => ['test' => 'phpunit'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $result = $this->checker()->check();

        static::assertSame(StatusResult::MANIFEST_PEELED, $result->manifestState);
    }

    public function testDetectsUnpeeledManifestWithBackup(): void
    {
        $manifest = [
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'require-dev' => ['phpunit/phpunit' => '^10'],
            'scripts' => ['test' => 'phpunit'],
        ];
        $this->writeComposerJson($manifest);
        file_put_contents(
            $this->testDir . '/.composer-unpeeled.json',
            file_get_contents($this->testDir . '/composer.json'),
        );

        $result = $this->checker()->check();

        static::assertSame(StatusResult::MANIFEST_UNPEELED, $result->manifestState);
    }

    public function testDetectsModifiedManifest(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.3'], // different from backup
        ]);

        file_put_contents(
            $this->testDir . '/.composer-unpeeled.json',
            (string) json_encode([
                'name' => 'test/pkg',
                'require' => ['php' => '>=8.2'],
                'require-dev' => ['phpunit/phpunit' => '^10'],
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) . "\n",
        );

        $result = $this->checker()->check();

        static::assertSame(StatusResult::MANIFEST_MODIFIED, $result->manifestState);
    }

    // ─── Backup state ──────────────────────────────────────────────────────

    public function testBackupExists(): void
    {
        $this->writeComposerJson(['name' => 'test/pkg', 'require' => ['php' => '>=8.2']]);
        file_put_contents($this->testDir . '/.composer-unpeeled.json', '{}');

        $result = $this->checker()->check();

        static::assertTrue($result->backupExists);
    }

    public function testBackupAbsent(): void
    {
        $this->writeComposerJson(['name' => 'test/pkg', 'require' => ['php' => '>=8.2']]);

        $result = $this->checker()->check();

        static::assertFalse($result->backupExists);
    }

    // ─── Package version ───────────────────────────────────────────────────

    public function testPackageVersionPresent(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'version' => '1.4.0',
            'require' => ['php' => '>=8.2'],
        ]);

        $result = $this->checker()->check();

        static::assertSame('1.4.0', $result->packageVersion);
    }

    public function testPackageVersionAbsent(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        $result = $this->checker()->check();

        static::assertNull($result->packageVersion);
    }

    // ─── Application version detection ─────────────────────────────────────

    public function testDetectsVersionFromBinFile(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/my-tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/my-tool', '<?php $v = "2.0.0";');

        $result = $this->checker()->check();

        static::assertCount(1, $result->applicationVersionSources);
        static::assertSame('bin/my-tool', $result->applicationVersionSources[0]->path);
        static::assertSame('2.0.0', $result->applicationVersionSources[0]->version);
    }

    public function testDetectsVersionFromConsoleApplication(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        mkdir($this->testDir . '/src/Console', 0777, true);
        file_put_contents($this->testDir . '/src/Console/Application.php', '<?php const VERSION = "3.1.0";');

        $result = $this->checker()->check();

        static::assertCount(1, $result->applicationVersionSources);
        static::assertSame('src/Console/Application.php', $result->applicationVersionSources[0]->path);
    }

    public function testDetectsVersionFromApplication(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        mkdir($this->testDir . '/src', 0777, true);
        file_put_contents($this->testDir . '/src/Application.php', '<?php $v = "1.0.0";');

        $result = $this->checker()->check();

        static::assertCount(1, $result->applicationVersionSources);
        static::assertSame('src/Application.php', $result->applicationVersionSources[0]->path);
    }

    public function testDetectsVersionFromServer(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        mkdir($this->testDir . '/src', 0777, true);
        file_put_contents($this->testDir . '/src/Server.php', '<?php $v = "1.2.3";');

        $result = $this->checker()->check();

        static::assertCount(1, $result->applicationVersionSources);
        static::assertSame('src/Server.php', $result->applicationVersionSources[0]->path);
    }

    public function testMultipleMatchingVersionSources(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.0.0";');

        mkdir($this->testDir . '/src/Console', 0777, true);
        file_put_contents($this->testDir . '/src/Console/Application.php', '<?php $v = "1.0.0";');

        $result = $this->checker()->check();

        static::assertCount(2, $result->applicationVersionSources);
        static::assertSame(StatusResult::VERSION_CONSISTENT, $result->versionConsistency);
        static::assertSame('1.0.0', $result->canonicalApplicationVersion);
    }

    public function testMultipleConflictingVersionSources(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.3.0";');

        mkdir($this->testDir . '/src/Console', 0777, true);
        file_put_contents($this->testDir . '/src/Console/Application.php', '<?php $v = "1.4.0";');

        $result = $this->checker()->check();

        static::assertCount(2, $result->applicationVersionSources);
        static::assertSame(StatusResult::VERSION_INCONSISTENT, $result->versionConsistency);
        static::assertNotEmpty($result->issues);
    }

    public function testNoApplicationVersionSource(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        $result = $this->checker()->check();

        static::assertCount(0, $result->applicationVersionSources);
        static::assertSame(StatusResult::VERSION_UNKNOWN, $result->versionConsistency);
        static::assertNull($result->canonicalApplicationVersion);
    }

    public function testPackageVersionNotTreatedAsApplicationVersionSource(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'version' => '9.9.9',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.0.0";');

        $result = $this->checker()->check();

        static::assertSame('9.9.9', $result->packageVersion);
        static::assertSame('1.0.0', $result->canonicalApplicationVersion);
        static::assertCount(1, $result->applicationVersionSources);
    }

    // ─── Git tag state ─────────────────────────────────────────────────────

    public function testMatchingGitTag(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $this->initGit();
        chdir($this->testDir);
        exec('git tag v1.4.0');
        chdir($this->originalDir);

        $result = $this->checker()->check();

        static::assertSame('v1.4.0', $result->latestGitTag);
        static::assertEmpty($result->issues);
    }

    public function testGitTagWithLeadingV(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "v1.4.0";');

        $this->initGit();
        chdir($this->testDir);
        exec('git tag v1.4.0');
        chdir($this->originalDir);

        $result = $this->checker()->check();

        // v1.4.0 in source normalized to 1.4.0, tag v1.4.0 normalized to 1.4.0 — should match
        static::assertEmpty($result->issues);
    }

    public function testMismatchingGitTag(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $this->initGit();
        chdir($this->testDir);
        exec('git tag v1.3.0');
        chdir($this->originalDir);

        $result = $this->checker()->check();

        static::assertSame('v1.3.0', $result->latestGitTag);
        static::assertNotEmpty($result->issues);
    }

    public function testNoGitTags(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $this->initGit();

        $result = $this->checker()->check();

        static::assertNull($result->latestGitTag);
    }

    // ─── CHANGELOG ─────────────────────────────────────────────────────────

    public function testMatchingChangelogEntry(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        file_put_contents($this->testDir . '/CHANGELOG.md', "## [1.4.0] - 2025-01-01\n\n- New feature\n");

        $result = $this->checker()->check();

        static::assertSame('1.4.0', $result->changelogVersion);
    }

    public function testMissingChangelogEntry(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        file_put_contents($this->testDir . '/CHANGELOG.md', "## [1.3.0] - 2024-12-01\n\n- Old feature\n");

        $result = $this->checker()->check();

        static::assertSame('1.3.0', $result->changelogVersion);
        // Should have an issue about CHANGELOG mismatch
        static::assertNotEmpty($result->issues);
    }

    public function testNoChangelogFile(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $result = $this->checker()->check();

        static::assertNull($result->changelogVersion);
    }

    // ─── Working tree ──────────────────────────────────────────────────────

    public function testCleanWorkingTree(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $this->initGit();

        $result = $this->checker()->check();

        static::assertTrue($result->workingTreeClean);
    }

    public function testModifiedWorkingTree(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        $this->initGit();

        // Add an unexpected change
        file_put_contents($this->testDir . '/notes.md', 'unexpected');

        $result = $this->checker()->check();

        static::assertFalse($result->workingTreeClean);
        static::assertNotEmpty($result->workingTreeChanges);
    }

    // ─── Overall release readiness ─────────────────────────────────────────

    public function testReadyReleaseState(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.4.0";');

        file_put_contents($this->testDir . '/CHANGELOG.md', "## [1.4.0] - 2025-01-01\n\n- New feature\n");

        $this->initGit();
        chdir($this->testDir);
        exec('git tag v1.4.0');
        chdir($this->originalDir);

        $result = $this->checker()->check();

        static::assertSame(StatusResult::RELEASE_READY, $result->releaseState);
        static::assertTrue($result->isReleaseReady());
    }

    public function testNotReadyReleaseStateNoAppVersion(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        $result = $this->checker()->check();

        static::assertSame(StatusResult::RELEASE_NOT_READY, $result->releaseState);
        static::assertFalse($result->isReleaseReady());
    }

    public function testNotReadyReleaseStateInconsistentVersions(): void
    {
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
            'bin' => ['bin/tool'],
        ]);

        mkdir($this->testDir . '/bin', 0777, true);
        file_put_contents($this->testDir . '/bin/tool', '<?php $v = "1.3.0";');

        mkdir($this->testDir . '/src/Console', 0777, true);
        file_put_contents($this->testDir . '/src/Console/Application.php', '<?php $v = "1.4.0";');

        $result = $this->checker()->check();

        static::assertSame(StatusResult::RELEASE_NOT_READY, $result->releaseState);
    }

    public function testPeelSectionsIncludedInResult(): void
    {
        $config = new Configuration();
        $config->setPeelSections(['require-dev', 'scripts']);

        $checker = new StatusChecker($config, $this->testDir);
        $this->writeComposerJson([
            'name' => 'test/pkg',
            'require' => ['php' => '>=8.2'],
        ]);

        $result = $checker->check();

        static::assertSame(['require-dev', 'scripts'], $result->peelSections);
    }
}
