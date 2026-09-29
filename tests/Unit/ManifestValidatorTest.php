<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ManifestValidator;

class ManifestValidatorTest extends TestCase
{
    private string $testDir;
    private string $originalDir;
    private ManifestValidator $validator;

    /** @var array<string, mixed> */
    private array $backupManifest = [
        'name' => 'test/package',
        'description' => 'A test package',
        'license' => 'MIT',
        'require' => ['php' => '>=8.2'],
        'autoload' => ['psr-4' => ['Test\\' => 'src/']],
        'require-dev' => ['phpunit/phpunit' => '^10.0'],
        'scripts' => ['test' => 'phpunit'],
    ];

    /** @var array<string, mixed> */
    private array $peeledManifest = [
        'name' => 'test/package',
        'description' => 'A test package',
        'license' => 'MIT',
        'require' => ['php' => '>=8.2'],
        'autoload' => ['psr-4' => ['Test\\' => 'src/']],
    ];

    protected function setUp(): void
    {
        $cwd = getcwd();
        if ($cwd === false) {
            throw new RuntimeException('Could not get current working directory.');
        }
        $this->originalDir = $cwd;
        $this->testDir = sys_get_temp_dir() . '/composer-peel-validator-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        $this->validator = new ManifestValidator(new Configuration());
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testValidatePassesForCorrectlyPeeledManifest(): void
    {
        $this->writeManifests($this->peeledManifest, $this->backupManifest);

        $this->validator->validate('composer.json', '.composer-unpeeled.json');

        $this->expectNotToPerformAssertions();
    }

    public function testValidateThrowsExceptionIfBackupDoesNotExist(): void
    {
        file_put_contents('composer.json', (string) json_encode($this->peeledManifest));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage(
            'Backup file .composer-unpeeled.json does not exist. Run the peel command before releasing.',
        );

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfManifestIsInvalidJson(): void
    {
        file_put_contents('composer.json', 'invalid json');
        file_put_contents('.composer-unpeeled.json', (string) json_encode($this->backupManifest));

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Manifest composer.json does not contain a valid JSON object.');

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfPeelSectionIsStillPresent(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'require-dev' => ['phpunit/phpunit' => '^10.0']],
            $this->backupManifest,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Section 'require-dev' has not been peeled.");

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfRuntimeSectionIsMissing(): void
    {
        $peeledManifest = $this->peeledManifest;
        unset($peeledManifest['autoload']);

        $this->writeManifests($peeledManifest, $this->backupManifest);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Section 'autoload' is missing.");

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfRuntimeSectionDiffersFromBackup(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'require' => ['php' => '>=8.3']],
            $this->backupManifest,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Section 'require' differs from the backup.");

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfSectionIsNotPresentInBackup(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'homepage' => 'https://example.com'],
            $this->backupManifest,
        );

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage("Section 'homepage' is not present in the backup.");

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateReportsAllViolations(): void
    {
        $this->writeManifests(
            ['name' => 'test/package', 'description' => 'A test package', 'license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
            $this->backupManifest,
        );

        try {
            $this->validator->validate('composer.json', '.composer-unpeeled.json');
            static::fail('Expected validation to fail.');
        } catch (RuntimeException $e) {
            static::assertStringContainsString("Section 'scripts' has not been peeled.", $e->getMessage());
            static::assertStringContainsString("Section 'require' is missing.", $e->getMessage());
            static::assertStringContainsString("Section 'autoload' is missing.", $e->getMessage());
        }
    }

    public function testValidateRespectsConfiguredPeelSections(): void
    {
        $configuration = new Configuration();
        $configuration->setPeelSections(['require-dev']);

        $this->writeManifests(
            [...$this->peeledManifest, 'scripts' => ['test' => 'phpunit']],
            $this->backupManifest,
        );

        (new ManifestValidator($configuration))->validate('composer.json', '.composer-unpeeled.json');

        $this->expectNotToPerformAssertions();
    }

    public function testValidateThrowsExceptionIfComposerValidateFails(): void
    {
        $peeledManifest = [...$this->peeledManifest, 'require' => ['php' => 'not-a-version']];
        $backupManifest = [...$this->backupManifest, 'require' => ['php' => 'not-a-version']];

        $this->writeManifests($peeledManifest, $backupManifest);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The peeled composer.json failed composer validate:');

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfComposerPublishCheckFails(): void
    {
        $peeledManifest = $this->peeledManifest;
        $backupManifest = $this->backupManifest;
        unset($peeledManifest['description'], $backupManifest['description']);

        $this->writeManifests($peeledManifest, $backupManifest);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('The property description is required');

        $this->validator->validate('composer.json', '.composer-unpeeled.json');
    }

    public function testValidateThrowsExceptionIfComposerIsNotAvailable(): void
    {
        $this->writeManifests($this->peeledManifest, $this->backupManifest);

        $validator = new ManifestValidator(new Configuration(), 'non-existent-composer-binary');

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Composer is not available or not installed.');

        $validator->validate('composer.json', '.composer-unpeeled.json');
    }

    /**
     * @param array<string, mixed> $peeledManifest
     * @param array<string, mixed> $backupManifest
     */
    private function writeManifests(array $peeledManifest, array $backupManifest): void
    {
        file_put_contents('composer.json', (string) json_encode($peeledManifest));
        file_put_contents('.composer-unpeeled.json', (string) json_encode($backupManifest));
    }
}
