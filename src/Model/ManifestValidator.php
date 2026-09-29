<?php

declare(strict_types=1);

namespace Stolt\ComposerPeel\Model;

use RuntimeException;

class ManifestValidator
{
    public const REQUIRED_RUNTIME_SECTIONS = ['name', 'require'];

    private Configuration $configuration;
    private string $composerBinary;
    private ?bool $composerAvailable = null;

    public function __construct(Configuration $configuration, string $composerBinary = 'composer')
    {
        $this->configuration = $configuration;
        $this->composerBinary = $composerBinary;
    }

    /**
     * Validates the peeled manifest for a release, which always requires a backup.
     *
     * @throws RuntimeException
     */
    public function validate(string $manifestPath, string $backupPath): void
    {
        if (!file_exists($backupPath)) {
            throw new RuntimeException(
                "Backup file {$backupPath} does not exist. Run the peel command before releasing.",
            );
        }

        $result = $this->check($manifestPath, $backupPath);

        if (!$result->isValid()) {
            throw new RuntimeException(
                'The peeled composer.json is invalid:' . PHP_EOL . '  - '
                . implode(PHP_EOL . '  - ', $result->getViolations()),
            );
        }
    }

    public function check(string $manifestPath, string $backupPath, bool $runComposerValidate = true): ValidationResult
    {
        $result = new ValidationResult();

        $manifest = $this->decodeManifest($manifestPath);
        if (is_string($manifest)) {
            $result->add(ValidationCheck::failed("{$manifestPath} contains valid JSON", [$manifest]));
        } else {
            $result->add(ValidationCheck::passed("{$manifestPath} contains valid JSON"));
        }

        $backup = $this->checkBackup($result, $backupPath);

        if (is_string($manifest)) {
            return $result;
        }

        $result->add($this->checkConfiguredSectionsAreAbsent($manifest));
        $result->add($this->checkRequiredRuntimeSectionsExist($manifest));

        if ($backup === null) {
            $result->add(ValidationCheck::skipped('Runtime sections match the backup', 'No backup available.'));
        } else {
            $result->add($this->checkRuntimeSectionsMatchBackup($manifest, $backup));
        }

        if (!$runComposerValidate) {
            $result->add(ValidationCheck::skipped('composer validate reports no errors', 'Skipped on request.'));
            return $result;
        }

        $result->add($this->checkComposerValidate($manifestPath));
        if ($backup !== null) {
            $result->add($this->checkComposerValidate($backupPath));
        }

        return $result;
    }

    /**
     * @return array<string, mixed>|null
     */
    private function checkBackup(ValidationResult $result, string $backupPath): ?array
    {
        if (!file_exists($backupPath)) {
            if ($this->configuration->isBackupEnabled()) {
                $result->add(ValidationCheck::failed(
                    "Backup {$backupPath} exists",
                    ["Backup file {$backupPath} does not exist."],
                ));
            } else {
                $result->add(ValidationCheck::skipped("Backup {$backupPath} exists", 'Backup is disabled.'));
            }

            return null;
        }

        $result->add(ValidationCheck::passed("Backup {$backupPath} exists"));

        $backup = $this->decodeManifest($backupPath);
        if (is_string($backup)) {
            $result->add(ValidationCheck::failed("Backup {$backupPath} contains valid JSON", [$backup]));
            return null;
        }

        $result->add(ValidationCheck::passed("Backup {$backupPath} contains valid JSON"));

        return $backup;
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function checkConfiguredSectionsAreAbsent(array $manifest): ValidationCheck
    {
        $violations = [];
        foreach ($this->configuration->getPeelSections() as $section) {
            if (!array_key_exists($section, $manifest)) {
                continue;
            }

            $violations[] = "Section '{$section}' has not been peeled.";
        }

        if ($violations !== []) {
            return ValidationCheck::failed('Configured sections are absent', $violations);
        }

        return ValidationCheck::passed('Configured sections are absent');
    }

    /**
     * @param array<string, mixed> $manifest
     */
    private function checkRequiredRuntimeSectionsExist(array $manifest): ValidationCheck
    {
        $violations = [];
        foreach (self::REQUIRED_RUNTIME_SECTIONS as $section) {
            if (array_key_exists($section, $manifest)) {
                continue;
            }

            $violations[] = "Required runtime section '{$section}' is missing.";
        }

        if ($violations !== []) {
            return ValidationCheck::failed('Required runtime sections exist', $violations);
        }

        return ValidationCheck::passed('Required runtime sections exist');
    }

    /**
     * @param array<string, mixed> $manifest
     * @param array<string, mixed> $backup
     */
    private function checkRuntimeSectionsMatchBackup(array $manifest, array $backup): ValidationCheck
    {
        $violations = [];
        $peelSections = $this->configuration->getPeelSections();
        $sections = array_unique([...array_keys($backup), ...array_keys($manifest)]);

        foreach ($sections as $section) {
            if (in_array($section, $peelSections, true)) {
                continue;
            }

            if (!array_key_exists($section, $manifest)) {
                $violations[] = "Section '{$section}' is missing.";
                continue;
            }

            if (!array_key_exists($section, $backup)) {
                $violations[] = "Section '{$section}' is not present in the backup.";
                continue;
            }

            if ($manifest[$section] === $backup[$section]) {
                continue;
            }

            $violations[] = "Section '{$section}' differs from the backup.";
        }

        if ($violations !== []) {
            return ValidationCheck::failed('Runtime sections match the backup', $violations);
        }

        return ValidationCheck::passed('Runtime sections match the backup');
    }

    private function checkComposerValidate(string $path): ValidationCheck
    {
        $description = "composer validate reports no errors for {$path}";

        if ($this->composerAvailable === null) {
            exec(escapeshellarg($this->composerBinary) . ' --version 2>&1', $output, $resultCode);
            $this->composerAvailable = $resultCode === 0;
        }

        if (!$this->composerAvailable) {
            return ValidationCheck::failed($description, ['Composer is not available or not installed.']);
        }

        $command = [$this->composerBinary, 'validate', '--no-check-lock', '--no-interaction', $path];
        $escapedCommand = implode(' ', array_map('escapeshellarg', $command));

        $output = [];
        exec($escapedCommand . ' 2>&1', $output, $resultCode);

        if ($resultCode !== 0) {
            return ValidationCheck::failed(
                $description,
                ["composer validate failed for {$path}:" . PHP_EOL . implode(PHP_EOL, $output)],
            );
        }

        return ValidationCheck::passed($description);
    }

    /**
     * @return array<string, mixed>|string The decoded manifest or an error message
     */
    private function decodeManifest(string $path): array|string
    {
        if (!file_exists($path)) {
            return "Manifest {$path} does not exist.";
        }

        $content = file_get_contents($path);
        if ($content === false) {
            return "Failed to read manifest {$path}.";
        }

        $manifest = json_decode($content, associative: true);
        if (json_last_error() !== JSON_ERROR_NONE || !is_array($manifest) || (array_is_list($manifest) && $manifest !== [])) {
            return "Manifest {$path} does not contain a valid JSON object.";
        }

        /** @var array<string, mixed> $manifest */
        return $manifest;
    }
}
