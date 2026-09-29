<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Unit;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Model\Configuration;
use Stolt\ComposerPeel\Model\ManifestValidator;
use Stolt\ComposerPeel\Model\ValidationCheck;
use Stolt\ComposerPeel\Model\ValidationResult;

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

    public function testCheckPassesAllChecksForCorrectlyPeeledManifest(): void
    {
        $this->writeManifests($this->peeledManifest, $this->backupManifest);

        $result = $this->validator->check('composer.json', '.composer-unpeeled.json');

        static::assertTrue($result->isValid());
        static::assertSame([], $result->getViolations());
        static::assertSame(
            [
                'composer.json contains valid JSON',
                'Backup .composer-unpeeled.json exists',
                'Backup .composer-unpeeled.json contains valid JSON',
                'Configured sections are absent',
                'Required runtime sections exist',
                'Runtime sections match the backup',
                'composer validate reports no errors for composer.json',
                'composer validate reports no errors for .composer-unpeeled.json',
            ],
            array_map(static fn(ValidationCheck $check): string => $check->getDescription(), $result->getChecks()),
        );
    }

    public function testCheckFailsIfPeelSectionIsStillPresent(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'require-dev' => ['phpunit/phpunit' => '^10.0']],
            $this->backupManifest,
        );

        $this->assertViolations(["Section 'require-dev' has not been peeled."]);
    }

    public function testCheckFailsIfConfiguredSectionIsRetainedWithoutBeingInBackup(): void
    {
        $backupManifest = $this->backupManifest;
        unset($backupManifest['scripts']);

        $this->writeManifests([...$this->peeledManifest, 'scripts' => ['test' => 'phpunit']], $backupManifest);

        $this->assertViolations(["Section 'scripts' has not been peeled."]);
    }

    public function testCheckFailsIfRequiredRuntimeSectionIsMissing(): void
    {
        $peeledManifest = $this->peeledManifest;
        $backupManifest = $this->backupManifest;
        unset($peeledManifest['require'], $backupManifest['require']);

        $this->writeManifests($peeledManifest, $backupManifest);

        $this->assertViolations(["Required runtime section 'require' is missing."]);
    }

    public function testCheckFailsIfRuntimeSectionIsMissing(): void
    {
        $peeledManifest = $this->peeledManifest;
        unset($peeledManifest['autoload']);

        $this->writeManifests($peeledManifest, $this->backupManifest);

        $this->assertViolations(["Section 'autoload' is missing."]);
    }

    public function testCheckFailsIfRuntimeSectionDiffersFromBackup(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'require' => ['php' => '>=8.3']],
            $this->backupManifest,
        );

        $this->assertViolations(["Section 'require' differs from the backup."]);
    }

    public function testCheckFailsIfSectionIsNotPresentInBackup(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'homepage' => 'https://example.com'],
            $this->backupManifest,
        );

        $this->assertViolations(["Section 'homepage' is not present in the backup."]);
    }

    public function testCheckReportsAllViolations(): void
    {
        $this->writeManifests(
            ['name' => 'test/package', 'description' => 'A test package', 'license' => 'MIT', 'scripts' => ['test' => 'phpunit']],
            $this->backupManifest,
        );

        $this->assertViolations([
            "Section 'scripts' has not been peeled.",
            "Required runtime section 'require' is missing.",
            "Section 'require' is missing.",
            "Section 'autoload' is missing.",
        ]);
    }

    public function testCheckFailsIfManifestIsJsonArray(): void
    {
        $this->writeManifests(['test/package'], $this->backupManifest);

        $this->assertViolations(['Manifest composer.json does not contain a valid JSON object.']);
    }

    public function testCheckFailsIfBackupIsExpectedButMissing(): void
    {
        file_put_contents('composer.json', (string) json_encode($this->peeledManifest));

        $result = $this->validator->check('composer.json', '.composer-unpeeled.json', runComposerValidate: false);

        static::assertFalse($result->isValid());
        static::assertSame(['Backup file .composer-unpeeled.json does not exist.'], $result->getViolations());
        static::assertSame(ValidationCheck::SKIPPED, $this->findCheck($result, 'Runtime sections match the backup')->getStatus());
    }

    public function testCheckSkipsBackupChecksIfBackupIsDisabled(): void
    {
        $configuration = new Configuration();
        $configuration->setBackupEnabled(false);

        file_put_contents('composer.json', (string) json_encode($this->peeledManifest));

        $result = (new ManifestValidator($configuration))->check(
            'composer.json',
            '.composer-unpeeled.json',
            runComposerValidate: false,
        );

        static::assertTrue($result->isValid());
        static::assertSame(
            ValidationCheck::SKIPPED,
            $this->findCheck($result, 'Backup .composer-unpeeled.json exists')->getStatus(),
        );
        static::assertSame(ValidationCheck::SKIPPED, $this->findCheck($result, 'Runtime sections match the backup')->getStatus());
    }

    public function testCheckFailsIfBackupIsInvalidJson(): void
    {
        file_put_contents('composer.json', (string) json_encode($this->peeledManifest));
        file_put_contents('.composer-unpeeled.json', 'invalid json');

        $this->assertViolations(['Manifest .composer-unpeeled.json does not contain a valid JSON object.']);
    }

    public function testCheckRespectsConfiguredPeelSections(): void
    {
        $configuration = new Configuration();
        $configuration->setPeelSections(['require-dev']);

        $this->writeManifests(
            [...$this->peeledManifest, 'scripts' => ['test' => 'phpunit']],
            $this->backupManifest,
        );

        $result = (new ManifestValidator($configuration))->check(
            'composer.json',
            '.composer-unpeeled.json',
            runComposerValidate: false,
        );

        static::assertTrue($result->isValid());
    }

    public function testValidateReportsAllViolations(): void
    {
        $this->writeManifests(
            [...$this->peeledManifest, 'require-dev' => ['phpunit/phpunit' => '^10.0'], 'require' => ['php' => '>=8.3']],
            $this->backupManifest,
        );

        try {
            $this->validator->validate('composer.json', '.composer-unpeeled.json');
            static::fail('Expected validation to fail.');
        } catch (RuntimeException $e) {
            static::assertStringStartsWith('The peeled composer.json is invalid:', $e->getMessage());
            static::assertStringContainsString("Section 'require-dev' has not been peeled.", $e->getMessage());
            static::assertStringContainsString("Section 'require' differs from the backup.", $e->getMessage());
        }
    }

    public function testValidateThrowsExceptionIfComposerValidateFails(): void
    {
        $peeledManifest = [...$this->peeledManifest, 'require' => ['php' => 'not-a-version']];
        $backupManifest = [...$this->backupManifest, 'require' => ['php' => 'not-a-version']];

        $this->writeManifests($peeledManifest, $backupManifest);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('composer validate failed for composer.json:');

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
     * @param array<int, string> $expectedViolations
     */
    private function assertViolations(array $expectedViolations): void
    {
        $result = $this->validator->check('composer.json', '.composer-unpeeled.json', runComposerValidate: false);

        static::assertFalse($result->isValid());
        static::assertSame($expectedViolations, $result->getViolations());
    }

    private function findCheck(ValidationResult $result, string $description): ValidationCheck
    {
        foreach ($result->getChecks() as $check) {
            if ($check->getDescription() === $description) {
                return $check;
            }
        }

        static::fail("Check '{$description}' not found.");
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
