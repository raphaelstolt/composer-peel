<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Tests\Integration;

use PHPUnit\Framework\TestCase;
use RuntimeException;
use Stolt\ComposerPeel\Command\ValidateCommand;
use Stolt\ComposerPeel\Model\ComposerPeeler;
use Stolt\ComposerPeel\Model\ConfigurationLoader;
use Zenstruck\Console\Test\TestCommand;

class ValidateCommandTest extends TestCase
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
        $this->testDir = sys_get_temp_dir() . '/composer-peel-validate-test-' . uniqid();
        mkdir($this->testDir);
        chdir($this->testDir);

        copy(from: $this->originalDir . '/tests/fixtures/composer.json', to: 'composer.json');
    }

    protected function tearDown(): void
    {
        chdir($this->originalDir);
        exec('rm -rf ' . escapeshellarg($this->testDir));
    }

    public function testExecuteValidateSucceedsForPeeledManifest(): void
    {
        (new ComposerPeeler())->peel();

        TestCommand::for(new ValidateCommand())
            ->execute()
            ->assertSuccessful()
            ->assertOutputContains('[PASS] composer.json contains valid JSON')
            ->assertOutputContains('[PASS] Backup .composer-unpeeled.json exists')
            ->assertOutputContains('[PASS] Backup .composer-unpeeled.json contains valid JSON')
            ->assertOutputContains('[PASS] Configured sections are absent')
            ->assertOutputContains('[PASS] Required runtime sections exist')
            ->assertOutputContains('[PASS] Runtime sections match the backup')
            ->assertOutputContains('[PASS] composer validate reports no errors for composer.json')
            ->assertOutputContains('[PASS] composer validate reports no errors for .composer-unpeeled.json')
            ->assertOutputContains('Peeled composer.json validated successfully.');
    }

    public function testExecuteValidateFailsForUnpeeledManifest(): void
    {
        TestCommand::for(new ValidateCommand())
            ->execute('--skip-composer-validate')
            ->assertStatusCode(1)
            ->assertOutputContains('[FAIL] Backup .composer-unpeeled.json exists')
            ->assertOutputContains('- Backup file .composer-unpeeled.json does not exist.')
            ->assertOutputContains('[FAIL] Configured sections are absent')
            ->assertOutputContains("- Section 'require-dev' has not been peeled.")
            ->assertOutputContains("- Section 'autoload-dev' has not been peeled.")
            ->assertOutputContains("- Section 'scripts' has not been peeled.")
            ->assertOutputContains('[SKIP] Runtime sections match the backup')
            ->assertOutputContains('Validation of the peeled composer.json failed.');
    }

    public function testExecuteValidateFailsForInvalidJson(): void
    {
        (new ComposerPeeler())->peel();
        file_put_contents('composer.json', 'invalid json');

        TestCommand::for(new ValidateCommand())
            ->execute()
            ->assertStatusCode(1)
            ->assertOutputContains('[FAIL] composer.json contains valid JSON')
            ->assertOutputContains('- Manifest composer.json does not contain a valid JSON object.')
            ->assertOutputNotContains('composer validate reports no errors')
            ->assertOutputContains('Validation of the peeled composer.json failed.');
    }

    public function testExecuteValidateFailsIfRuntimeSectionWasRemoved(): void
    {
        (new ComposerPeeler())->peel();

        $manifest = json_decode((string) file_get_contents('composer.json'), associative: true);
        static::assertIsArray($manifest);
        unset($manifest['require'], $manifest['autoload']);
        file_put_contents('composer.json', (string) json_encode($manifest));

        TestCommand::for(new ValidateCommand())
            ->execute('--skip-composer-validate')
            ->assertStatusCode(1)
            ->assertOutputContains('[FAIL] Required runtime sections exist')
            ->assertOutputContains("- Required runtime section 'require' is missing.")
            ->assertOutputContains('[FAIL] Runtime sections match the backup')
            ->assertOutputContains("- Section 'autoload' is missing.");
    }

    public function testExecuteValidateFailsIfBackupIsInvalidJson(): void
    {
        (new ComposerPeeler())->peel();
        file_put_contents('.composer-unpeeled.json', 'invalid json');

        TestCommand::for(new ValidateCommand())
            ->execute('--skip-composer-validate')
            ->assertStatusCode(1)
            ->assertOutputContains('[FAIL] Backup .composer-unpeeled.json contains valid JSON')
            ->assertOutputContains('[SKIP] Runtime sections match the backup');
    }

    public function testExecuteValidateSkipsBackupChecksIfBackupIsDisabled(): void
    {
        file_put_contents('.composer-peel.php', <<<'PHP'
            <?php

            return [
                'release' => [
                    'backup' => [
                        'enabled' => false,
                    ],
                ],
            ];
            PHP);

        (new ComposerPeeler((new ConfigurationLoader())->load('.composer-peel.php')))->peel();

        static::assertFileDoesNotExist('.composer-unpeeled.json');

        TestCommand::for(new ValidateCommand())
            ->execute('--skip-composer-validate')
            ->assertSuccessful()
            ->assertOutputContains('[SKIP] Backup .composer-unpeeled.json exists')
            ->assertOutputContains('- Backup is disabled.')
            ->assertOutputContains('[SKIP] composer validate reports no errors')
            ->assertOutputContains('Peeled composer.json validated successfully.');
    }

    public function testExecuteValidateWithCustomBackupFile(): void
    {
        (new ComposerPeeler())->peel();
        rename('.composer-unpeeled.json', 'custom-backup.json');

        TestCommand::for(new ValidateCommand())
            ->execute('--backup-file=custom-backup.json --skip-composer-validate')
            ->assertSuccessful()
            ->assertOutputContains('[PASS] Backup custom-backup.json exists');
    }
}
